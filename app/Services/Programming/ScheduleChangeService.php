<?php

namespace App\Services\Programming;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Programming\ChangeReason;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ScheduleChange;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScheduleChangeService
{
    public const FIELDS = [
        'shift' => ['shift_id', 'shift_name', 'shift_start_time', 'shift_end_time'],
        'vehicle' => ['vehicle_id', 'vehicle_label'],
        'driver' => ['driver_id', 'driver_name'],
        'helper' => ['helper_ids', 'helper_names'],
    ];

    public function lock(): void
    {
        // A common resource lock serializes creation, changes, reversals and run starts.
        Shift::orderBy('id')->lockForUpdate()->get();
    }

    public function assertEditable(ProgrammingSchedule $schedule, string $date): void
    {
        if (! $schedule->runsOn($date)) {
            $this->fail('La fecha no pertenece a esta programación.');
        }
        if (! in_array($schedule->status, ['Programada', 'Reprogramado'], true)
            || $schedule->runs()->whereDate('service_date', $date)->whereNotNull('started_at')->exists()
            || Carbon::parse($date.' '.$schedule->shift_start_time, 'America/Lima')->lte(now('America/Lima'))) {
            $this->fail('Solo se pueden cambiar recorridos que aún no inician.');
        }
    }

    public function attended(int $employeeId, string $date): bool
    {
        return Attendance::where('employee_id', $employeeId)->whereDate('date', $date)
            ->where('status', 'present')->where('type', 'entry')
            ->when($date === now('America/Lima')->toDateString(), fn ($q) => $q->where('time', '<=', now('America/Lima')->format('H:i:s')))->exists();
    }

    public function assertCrew(int $driverId, array $helpers, Vehicle $vehicle, int $zoneId): void
    {
        if (! Zone::whereKey($zoneId)->where('status', true)->exists()) {
            $this->fail('La zona debe estar activa.');
        }
        if (count($helpers) === 0 || count($helpers) !== count(array_unique($helpers)) || in_array($driverId, $helpers, true)) {
            $this->fail('Seleccione ayudantes distintos del conductor y entre sí.');
        }
        foreach (['Conductor' => [$driverId], 'Ayudante' => $helpers] as $role => $ids) {
            if (Employee::whereIn('id', $ids)->where('status', true)->whereHas('employeeType', fn ($q) => $q->where('name', $role))->count() !== count($ids)) {
                $this->fail('El personal debe estar activo y corresponder al tipo '.$role.'.');
            }
        }
        if (count($helpers) + 1 > $vehicle->occupant_capacity) {
            $this->fail('El personal supera la capacidad del vehículo.');
        }
    }

    public function definition(ProgrammingSchedule $schedule, string $date): array
    {
        return array_merge($schedule->only(array_merge(...array_values(self::FIELDS))), [
            'group_id' => $schedule->personnel_group_id, 'group_name' => $schedule->group_name,
            'zone_id' => $schedule->zone_id, 'zone_name' => $schedule->zone_name,
            'start_date' => $date, 'end_date' => $date, 'days_of_week' => [Carbon::parse($date)->dayOfWeekIso],
        ]);
    }

    public function replacement(ProgrammingSchedule $schedule, string $type, int $oldId, int $newId): array
    {
        if ($oldId === $newId) {
            $this->fail('El valor nuevo debe ser diferente del anterior.');
        }
        if ($type !== 'helper' && (int) $schedule->{$type.'_id'} !== $oldId) {
            $this->fail('El valor anterior cambió. Actualice la página.');
        }
        if ($type === 'shift') {
            $value = Shift::findOrFail($newId);

            return ['shift_id' => $value->id, 'shift_name' => $value->name, 'shift_start_time' => $value->start_time, 'shift_end_time' => $value->end_time];
        }
        if ($type === 'vehicle') {
            $value = Vehicle::findOrFail($newId);

            return ['vehicle_id' => $value->id, 'vehicle_label' => trim($value->plate.' · '.$value->name)];
        }
        $employee = Employee::findOrFail($newId);
        if ($type === 'driver') {
            return ['driver_id' => $employee->id, 'driver_name' => $employee->full_name];
        }
        $ids = array_map('intval', $schedule->helper_ids);
        $position = array_search($oldId, $ids, true);
        if ($position === false) {
            $this->fail('El ayudante anterior ya no está asignado.');
        }
        $ids[$position] = $newId;
        $employees = Employee::whereIn('id', $ids)->get()->keyBy('id');

        return ['helper_ids' => $ids, 'helper_names' => array_map(fn ($id) => $employees[$id]->full_name, $ids)];
    }

    public function validateCandidate(ProgrammingSchedule $original, ProgrammingSchedule $candidate, string $date, string $source): void
    {
        $this->assertCrew((int) $candidate->driver_id, array_map('intval', $candidate->helper_ids), Vehicle::findOrFail($candidate->vehicle_id), $candidate->zone_id);
        if (Carbon::parse($date.' '.$candidate->shift_start_time, 'America/Lima')->lte(now('America/Lima'))) {
            $this->fail('El nuevo turno debe comenzar en el futuro.');
        }
        $oldCrew = array_map('intval', array_merge([$original->driver_id], $original->helper_ids));
        $newCrew = array_map('intval', array_merge([$candidate->driver_id], $candidate->helper_ids));
        if ($source === 'dashboard') {
            if ($date !== now('America/Lima')->toDateString()) {
                $this->fail('El dashboard solo permite cambios para hoy.');
            }
            foreach (array_diff($oldCrew, $newCrew) as $id) {
                if ($this->attended($id, $date)) {
                    $this->fail('No puede cambiar a personal que sí asistió.');
                }
            }
            foreach (array_diff($newCrew, $oldCrew) as $id) {
                if (! $this->attended($id, $date)) {
                    $this->fail('El reemplazo debe haber registrado su asistencia de entrada.');
                }
            }
            foreach (ProgrammingSchedule::where('id', '!=', $original->id)->where('shift_id', $candidate->shift_id)->get() as $other) {
                if ($other->runsOn($date) && array_intersect(array_diff($newCrew, $oldCrew), array_merge([$other->driver_id], $other->helper_ids))) {
                    $this->fail('El reemplazo ya tiene un recorrido asignado en este día y turno.');
                }
            }
        }
        // An unresolved absence elsewhere in the crew must not prevent replacing another absentee.
        $ignoredAbsences = $source === 'dashboard' || $source === 'revert' ? array_values(array_intersect($oldCrew, $newCrew)) : [];
        if ($source === 'revert') {
            $ignoredAbsences = $newCrew;
        }
        $result = app(ScheduleAvailabilityService::class)->check([$this->definition($candidate, $date)], $original->id, $ignoredAbsences, $date);
        if ($result['conflicts'] !== [] || $result['hard_conflicts'] !== []) {
            $this->fail(implode(' ', array_merge($result['hard_conflicts'], $result['conflicts'])));
        }
    }

    public function isolateDay(ProgrammingSchedule $schedule, string $date): ProgrammingSchedule
    {
        if ($schedule->start_date->toDateString() === $date && $schedule->end_date->toDateString() === $date) {
            return $schedule;
        }
        $child = $schedule->replicate();
        $child->parent_schedule_id = $schedule->id;
        $child->start_date = $date;
        $child->end_date = $date;
        $child->days_of_week = [Carbon::parse($date)->dayOfWeekIso];
        $child->excluded_dates = [];
        $child->save();
        $schedule->update(['excluded_dates' => array_values(array_unique(array_merge($schedule->excluded_dates ?? [], [$date])))]);
        // Carry creation history to the isolated day so its detail remains self-contained.
        foreach ($schedule->histories()->whereIn('action', ['created', 'created_mass'])->get() as $history) {
            $copy = $history->replicate();
            $copy->programming_schedule_id = $child->id;
            $copy->save();
        }

        return $child;
    }

    public function applyDay(ProgrammingSchedule $schedule, string $date, array $edits, string $source, ?string $operation = null): ProgrammingSchedule
    {
        $this->assertEditable($schedule, $date);
        $candidate = clone $schedule;
        $prepared = [];
        foreach ($edits as $edit) {
            $reason = ChangeReason::findOrFail($edit['reason_id']);
            $type = $edit['type'];
            $before = $candidate->only(self::FIELDS[$type]);
            $after = $this->replacement($candidate, $type, (int) $edit['old_id'], (int) $edit['new_id']);
            $candidate->fill($after);
            $prepared[] = compact('type', 'before', 'after', 'reason', 'edit');
        }
        if ($prepared === []) {
            $this->fail('Agregue al menos un cambio.');
        }
        $this->validateCandidate($schedule, $candidate, $date, $source);
        $schedule = $this->isolateDay($schedule, $date);
        $operation ??= (string) Str::uuid();
        foreach ($prepared as $item) {
            $beforeSnapshot = $this->snapshot($schedule);
            $schedule->fill($item['after']);
            $schedule->status = 'Reprogramado';
            $schedule->save();
            $change = ScheduleChange::create([
                'operation_uuid' => $operation, 'programming_schedule_id' => $schedule->id, 'service_date' => $date,
                'type' => $item['type'], 'source' => $source, 'change_reason_id' => $item['reason']->id,
                'reason_name' => $item['reason']->name, 'detail' => $item['edit']['detail'] ?? '',
                'before_data' => $item['before'], 'after_data' => $item['after'], 'changed_by' => auth()->id(),
            ]);
            $this->history($schedule, 'updated', $beforeSnapshot, $this->snapshot($schedule),
                'Motivo: '.$change->reason_name.' — '.$change->detail.' ['.$source.' #'.$change->id.'; '.$date.']');
        }

        return $schedule;
    }

    public function revert(ScheduleChange $change): void
    {
        DB::transaction(function () use ($change) {
            $this->lock();
            $change = ScheduleChange::lockForUpdate()->findOrFail($change->id);
            if ($change->reverted_at) {
                $this->fail('Este cambio ya fue revertido.');
            }
            $schedule = ProgrammingSchedule::lockForUpdate()->findOrFail($change->programming_schedule_id);
            $date = $change->service_date->toDateString();
            $this->assertEditable($schedule, $date);
            if ($schedule->changes()->where('type', $change->type)->where('id', '>', $change->id)->whereNull('reverted_at')->exists()) {
                $this->fail('Revierta primero los cambios posteriores del mismo tipo.');
            }
            if ($schedule->only(self::FIELDS[$change->type]) != $change->after_data) {
                $this->fail('El valor actual no coincide con el cambio. Revise los cambios posteriores.');
            }
            $candidate = clone $schedule;
            $candidate->fill($change->before_data);
            $this->validateCandidate($schedule, $candidate, $date, 'revert');
            $before = $this->snapshot($schedule);
            $schedule->update($change->before_data);
            $change->update(['reverted_at' => now(), 'reverted_by' => auth()->id()]);
            $this->history($schedule, 'reverted', $before, $this->snapshot($schedule), 'Reversión del cambio #'.$change->id.'. Motivo original: '.$change->reason_name.'; '.$date);
        }, 3);
    }

    public function snapshot(ProgrammingSchedule $schedule): array
    {
        return ['grupo' => $schedule->group_name, 'periodo' => $schedule->start_date->format('d/m/Y').' - '.$schedule->end_date->format('d/m/Y'),
            'turno' => $schedule->shift_name.' ('.substr($schedule->shift_start_time, 0, 5).'–'.substr($schedule->shift_end_time, 0, 5).')',
            'zona' => $schedule->zone_name, 'vehiculo' => $schedule->vehicle_label, 'conductor' => $schedule->driver_name,
            'ayudantes' => implode(', ', $schedule->helper_names), 'estado' => $schedule->status];
    }

    public function history(ProgrammingSchedule $schedule, string $action, ?array $before, ?array $after, string $notes): void
    {
        $schedule->histories()->create(['schedule_label' => $schedule->group_name.' · '.$schedule->start_date->format('d/m/Y'),
            'changed_by' => auth()->id(), 'action' => $action, 'before_data' => $before, 'after_data' => $after, 'notes' => $notes]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['change' => $message]);
    }
}
