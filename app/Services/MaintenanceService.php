<?php

namespace App\Services;

use App\Models\Employees\Employee;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MaintenanceService
{
    public function savePeriod(?Maintenance $maintenance, array $data): void
    {
        $this->write(function () use ($maintenance, $data) {
            $period = $maintenance
                ? Maintenance::findOrFail($maintenance->id)
                : new Maintenance;

            $overlap = Maintenance::query()
                ->when($period->exists, fn (Builder $query) => $query->whereKeyNot($period->id))
                ->where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date'])
                ->first();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_date' => "El período se cruza con «{$overlap->name}».",
                ]);
            }

            $period->fill($data);
            if ($period->exists && $period->isDirty(['start_date', 'end_date'])) {
                foreach ($period->schedules()->get() as $schedule) {
                    $this->syncDays(
                        $schedule, $this->firstDate($period, $schedule->weekday, 'end_date'),
                        $period->end_date, 'end_date'
                    );
                }
            }

            $period->save();
        });
    }

    public function saveSchedule(Maintenance $maintenance, ?MaintenanceSchedule $schedule, array $data): void
    {
        $this->write(function () use ($maintenance, $schedule, $data) {
            $period = Maintenance::findOrFail($maintenance->id);
            $current = $schedule
                ? $period->schedules()->findOrFail($schedule->id)
                : $period->schedules()->make();

            // Use the database's canonical TIME format for comparison on both SQLite and MySQL.
            $data['start_time'] .= ':00';
            $data['end_time'] .= ':00';
            $current->fill($data);

            if ($current->exists && ! $current->isDirty()) {
                return;
            }

            if (! Employee::whereKey($data['employee_id'])->where('status', true)->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Seleccione un responsable activo.']);
            }

            if ($current->exists && $this->recorded($current->days()->getQuery())->exists()) {
                throw ValidationException::withMessages([
                    'weekday' => 'Este horario tiene días con seguimiento. Conserve el horario original.',
                ]);
            }

            $firstDate = $this->firstDate($period, $current->weekday, 'weekday');
            $conflict = MaintenanceSchedule::query()
                ->when($current->exists, fn (Builder $query) => $query->whereKeyNot($current->id))
                ->where('weekday', $current->weekday)
                ->where('start_time', '<', $current->end_time)
                ->where('end_time', '>', $current->start_time)
                ->whereHas('days', fn (Builder $query) => $query->whereBetween(
                    'date', [$firstDate->toDateString(), $period->end_date->toDateString()]
                ));

            $errors = [];
            if ((clone $conflict)->where('vehicle_id', $current->vehicle_id)->exists()) {
                $errors['vehicle_id'] = 'El vehículo ya tiene un mantenimiento que coincide con este horario.';
            }
            if ((clone $conflict)->where('employee_id', $current->employee_id)->exists()) {
                $errors['employee_id'] = 'El responsable ya tiene un mantenimiento que coincide con este horario.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $weekdayChanged = $current->exists && $current->isDirty('weekday');
            $current->save();
            $this->syncDays($current, $firstDate, $period->end_date, 'weekday', $weekdayChanged);
        });
    }

    public function deletePeriod(Maintenance $maintenance): void
    {
        $this->write(function () use ($maintenance) {
            $period = Maintenance::findOrFail($maintenance->id);
            if ($period->schedules()->exists()) {
                abort(409, 'El mantenimiento tiene horarios. Elimine sus horarios antes de eliminarlo.');
            }

            $period->delete();
        });
    }

    public function deleteSchedule(Maintenance $maintenance, MaintenanceSchedule $schedule): void
    {
        $images = $this->write(function () use ($maintenance, $schedule) {
            $current = $maintenance->schedules()->findOrFail($schedule->id);
            $paths = $current->days()->whereNotNull('image_path')->pluck('image_path')->all();
            $current->delete();

            return $paths;
        });

        Storage::disk('local')->delete($images);
    }

    public function updateDay(MaintenanceSchedule $schedule, MaintenanceDay $day, array $data, ?UploadedFile $file): void
    {
        $disk = Storage::disk('local');
        $path = $file?->store('maintenance-evidence', 'local');
        if ($file && ! $path) {
            abort(500, 'No se pudo guardar la imagen.');
        }

        try {
            $oldImage = $this->write(function () use ($schedule, $day, $data, $path) {
                $current = $schedule->days()->findOrFail($day->id);
                if ($data['status'] !== 'pending' && $current->date->toDateString() > now('America/Lima')->toDateString()) {
                    throw ValidationException::withMessages(['status' => 'El estado solo se puede registrar a partir del día programado.']);
                }

                $previous = $current->image_path;
                $current->update([
                    'status' => $data['status'],
                    'observation' => $data['observation'] ?? null,
                    'image_path' => $path ?? $previous,
                ]);

                return $previous;
            });
        } catch (Throwable $exception) {
            if ($path) {
                $disk->delete($path);
            }

            throw $exception;
        }

        if ($path && $oldImage) {
            $disk->delete($oldImage);
        }
    }

    private function write(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Serialize conflict checks and writes, including when there are no matching rows.
            DB::table('maintenance_write_lock')->where('id', 1)->increment('version');

            return $callback();
        }, 5);
    }

    private function firstDate(Maintenance $maintenance, int $weekday, string $field): CarbonImmutable
    {
        $date = $maintenance->start_date;
        $date = $date->addDays(($weekday - $date->dayOfWeekIso + 7) % 7);
        if ($date->gt($maintenance->end_date)) {
            $day = mb_strtolower(MaintenanceSchedule::WEEKDAYS[$weekday]);
            throw ValidationException::withMessages([
                $field => "El período debe contener al menos un {$day}.",
            ]);
        }

        return $date;
    }

    private function syncDays(
        MaintenanceSchedule $schedule,
        CarbonImmutable $first,
        CarbonImmutable $last,
        string $field,
        bool $weekdayChanged = false
    ): void {
        $removed = $schedule->days();
        if (! $weekdayChanged) {
            $removed->whereNotBetween('date', [$first->toDateString(), $last->toDateString()]);
        }
        if ($this->recorded(clone $removed->getQuery())->exists()) {
            throw ValidationException::withMessages([
                $field => 'El cambio eliminaría días que ya tienen seguimiento.',
            ]);
        }

        $removed->delete();
        $existing = $schedule->days()->toBase()->pluck('date')->flip();
        $timestamp = now()->toDateTimeString();
        $batch = [];
        for ($date = $first; $date->lte($last); $date = $date->addWeek()) {
            $value = $date->toDateString();
            if ($existing->has($value)) {
                continue;
            }
            $batch[] = [
                'maintenance_schedule_id' => $schedule->id,
                'date' => $value,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            if (count($batch) === 200) {
                MaintenanceDay::insert($batch);
                $batch = [];
            }
        }
        if ($batch) {
            MaintenanceDay::insert($batch);
        }
    }

    private function recorded(Builder $query): Builder
    {
        return $query->where(fn (Builder $day) => $day
            ->where('status', '!=', 'pending')
            ->orWhereNotNull('observation')
            ->orWhereNotNull('image_path'));
    }
}
