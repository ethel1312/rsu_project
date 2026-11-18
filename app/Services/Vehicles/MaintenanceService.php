<?php

namespace App\Services\Vehicles;

use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function lockCalendar(): void
    {
        DB::table('maintenance_calendar_locks')->where('id', 1)->lockForUpdate()->first();
    }

    public function savePeriod(array $data, ?Maintenance $maintenance = null): Maintenance
    {
        return DB::transaction(function () use ($data, $maintenance) {
            $this->lockCalendar();
            if (Maintenance::where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date'])
                ->when($maintenance, fn ($q) => $q->whereKeyNot($maintenance->id))->exists()) {
                throw ValidationException::withMessages(['start_date' => 'Las fechas se superponen con otro mantenimiento registrado.']);
            }
            $maintenance = $maintenance ? Maintenance::findOrFail($maintenance->id) : new Maintenance;
            $maintenance->fill($data)->save();
            foreach ($maintenance->schedules()->get() as $schedule) {
                $this->syncDays($schedule, $maintenance);
            }

            return $maintenance;
        }, 3);
    }

    public function saveSchedule(Maintenance $maintenance, array $data, ?MaintenanceSchedule $schedule = null): MaintenanceSchedule
    {
        $data['start_time'] = substr($data['start_time'], 0, 5).':00';
        $data['end_time'] = substr($data['end_time'], 0, 5).':00';
        return DB::transaction(function () use ($maintenance, $data, $schedule) {
            $this->lockCalendar();
            $maintenance = Maintenance::findOrFail($maintenance->id);
            $schedule = $schedule ? $maintenance->schedules()->findOrFail($schedule->id) : new MaintenanceSchedule;
            $schedule->fill($data);
            if ($schedule->exists && $schedule->isDirty() && $schedule->days()->get()->contains(fn ($day) => $day->hasEvidence())) {
                throw ValidationException::withMessages(['weekday' => 'Este horario tiene seguimiento registrado. Para conservar su historial, cree otro horario o elimínelo mediante la confirmación correspondiente.']);
            }
            $dates = $this->dates($maintenance, $data['weekday']);
            $conflict = MaintenanceSchedule::where('vehicle_id', $data['vehicle_id'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->when($schedule->exists, fn ($q) => $q->whereKeyNot($schedule->id))
                ->whereHas('days', fn ($q) => $q->whereIn('date', $dates))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['start_time' => 'El vehículo ya tiene un mantenimiento en ese día y rango horario.']);
            }
            $maintenance->schedules()->save($schedule);
            $this->syncDays($schedule, $maintenance);

            return $schedule;
        }, 3);
    }

    private function dates(Maintenance $maintenance, int $weekday): array
    {
        $date = CarbonImmutable::parse($maintenance->start_date);
        $end = CarbonImmutable::parse($maintenance->end_date);
        $date = $date->addDays(($weekday - $date->dayOfWeekIso + 7) % 7);
        $dates = [];
        for (; $date->lte($end); $date = $date->addWeek()) {
            $dates[] = $date->toDateString();
        }
        if ($dates === []) {
            throw ValidationException::withMessages(['weekday' => 'El rango de fechas no contiene el día de la semana seleccionado.']);
        }

        return $dates;
    }

    private function syncDays(MaintenanceSchedule $schedule, Maintenance $maintenance): void
    {
        $dates = $this->dates($maintenance, $schedule->weekday);
        $removed = $schedule->days()->whereNotIn('date', $dates)->get();
        if ($removed->contains(fn ($day) => $day->hasEvidence())) {
            throw ValidationException::withMessages(['start_date' => 'El cambio quitaría días con observaciones, imágenes o estado registrado. Conserve esas fechas para no perder el seguimiento.']);
        }
        $schedule->days()->whereNotIn('date', $dates)->delete();
        foreach ($dates as $date) {
            $schedule->days()->firstOrCreate(['date' => $date]);
        }
    }
}
