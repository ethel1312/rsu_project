<?php

namespace App\Services\Programming;

use App\Models\Employees\Vacation;
use App\Models\Programming\Holiday;
use App\Models\Programming\ProgrammingSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleAvailabilityService
{
    public function check(array $definitions, ?int $excludeScheduleId = null, ?string $excludeDate = null): array
    {
        $employeeIds = collect($definitions)
            ->flatMap(fn ($item) => array_merge([$item['driver_id']], $item['helper_ids']))
            ->unique()->values();
        $minDate = collect($definitions)->min('start_date');
        $maxDate = collect($definitions)->max('end_date');

        $holidays = Holiday::active()->whereBetween('date', [$minDate, $maxDate])
            ->when(isset($definitions[0]['holiday_ids']), fn ($q) => $q->whereIn('id', $definitions[0]['holiday_ids']))
            ->pluck('description', 'date')
            ->mapWithKeys(fn ($description, $date) => [Carbon::parse($date)->toDateString() => $description]);

        $vacations = Vacation::with('employee')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['Pendiente', 'Aprobada'])
            ->whereDate('start_date', '<=', $maxDate)
            ->whereDate('end_date', '>=', $minDate)
            ->get(['employee_id', 'start_date', 'end_date', 'status']);

        $absences = collect();
        if (Schema::hasTable('attendances')) {
            $absences = DB::table('attendances')->whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$minDate, $maxDate])->where('status', 'absent')
                ->get(['employee_id', 'date'])->groupBy(fn ($row) => $row->date);
        }

        $existing = ProgrammingSchedule::query()->whereIn('status', ['Programada', 'Reprogramado'])
            ->whereDate('start_date', '<=', Carbon::parse($maxDate)->addDay()->toDateString())->whereDate('end_date', '>=', Carbon::parse($minDate)->subDay()->toDateString())
            ->when($excludeScheduleId && $excludeDate === null, fn ($query) => $query->where('id', '!=', $excludeScheduleId))
            ->get();

        $conflicts = [];
        $warnings = [];
        $plannedSlots = [];
        $serviceDays = 0;
        $skippedDays = 0;
        $acceptedDefinitions = [];
        $omissions = [];

        foreach ($definitions as $definition) {
            $dates = $this->serviceDates($definition);
            if ($dates === []) {
                $omissions[] = 'El rango no contiene días seleccionados para «'.$definition['group_name'].'».';
                continue;
            }

            $excludedDates = [];
            $reasonsByDate = [];
            $pendingWarnings = [];
            foreach ($dates as $date) {
                $dayConflicts = [];
                $crew = array_values(array_unique(array_merge([$definition['driver_id']], $definition['helper_ids'])));

                if ($holidays->has($date)) {
                    $this->addConflict($dayConflicts, $date.': feriado activo «'.$holidays->get($date).'».');
                }

                foreach ($vacations as $vacation) {
                    if (! in_array((int) $vacation->employee_id, $crew, true)) continue;
                    if ($date < $vacation->start_date->toDateString() || $date > $vacation->end_date->toDateString()) continue;

                    $employeeName = $vacation->employee?->full_name ?? 'Un integrante del grupo';
                    $period = 'del '.$vacation->start_date->format('d/m/Y').' al '.$vacation->end_date->format('d/m/Y');
                    if ($vacation->status === 'Pendiente') {
                        $pendingWarnings[] = '«'.$definition['group_name'].'»: '.$employeeName.' tiene vacaciones sin confirmar '.$period.'. Se mantienen esos días; consulte el estado de la solicitud y vuelva a validar si se aprueba.';
                    } else {
                        $this->addConflict($dayConflicts, $date.': '.$employeeName.' tiene vacaciones aprobadas '.$period.'.');
                    }
                }

                foreach ($absences->get($date, collect()) as $absence) {
                    if (in_array((int) $absence->employee_id, $crew, true)) {
                        $this->addConflict($dayConflicts, $date.': un integrante del grupo registra una inasistencia.');
                    }
                }

                $slot = [
                    'date' => $date,
                    'group_id' => $definition['group_id'],
                    'vehicle_id' => $definition['vehicle_id'],
                    'crew' => $crew,
                    'start' => $definition['shift_start_time'],
                    'end' => $definition['shift_end_time'],
                    'label' => $definition['group_name'],
                ];

                foreach ($existing as $other) {
                    foreach ([-1, 0, 1] as $dayOffset) {
                        $otherDate = Carbon::parse($date)->addDays($dayOffset)->toDateString();
                        if ($other->id === $excludeScheduleId && $otherDate === $excludeDate) continue;
                        if (! $this->runsOn($other, $otherDate)) continue;
                        $otherSlot = $this->slotFromSchedule($other, $otherDate);
                        $otherSlot['day_offset'] = $dayOffset;
                        $this->checkOverlap($dayConflicts, $slot, $otherSlot);
                    }
                }
                foreach ($plannedSlots as $otherSlot) {
                    $dayDifference = (int) ((strtotime($otherSlot['date']) - strtotime($date)) / 86400);
                    if (abs($dayDifference) <= 1) {
                        $otherSlot['day_offset'] = $dayDifference;
                        $this->checkOverlap($dayConflicts, $slot, $otherSlot);
                    }
                }
                if ($dayConflicts !== []) {
                    $excludedDates[] = $date;
                    foreach (array_unique($dayConflicts) as $reason) {
                        // Group the same cause across dates instead of repeating vacations daily.
                        $reason = preg_replace('/^\d{4}-\d{2}-\d{2}: /', '', $reason);
                        $reasonsByDate[$reason][] = $date;
                    }
                } else {
                    $serviceDays++;
                    // Omitted days must not reserve crew or vehicles for later groups in the batch.
                    $plannedSlots[] = $slot;
                }
            }
            $warnings = array_merge($warnings, array_unique($pendingWarnings));
            foreach ($reasonsByDate as $reason => $affectedDates) {
                $omissions[] = '«'.$definition['group_name'].'»: no se generará programación '.$this->dateRanges($affectedDates).' porque '.$reason;
            }
            $skippedDays += count($excludedDates);
            if (count($excludedDates) < count($dates)) {
                $definition['excluded_dates'] = $excludedDates;
                $acceptedDefinitions[] = $definition;
            }
        }
        if ($serviceDays === 0) {
            $conflicts[] = 'No hay días disponibles para generar la programación. Revise las fechas, los días seleccionados o el personal.';
        }

        $conflicts = array_slice(array_values(array_unique($conflicts)), 0, 100);
        $warnings = array_slice(array_values(array_unique($warnings)), 0, 100);

        return [
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'suggestions' => $this->suggestionsFor(array_merge($conflicts, $omissions), $warnings),
            'service_days' => $serviceDays,
            'skipped_days' => $skippedDays,
            'omissions' => $omissions,
            'definitions' => $acceptedDefinitions,
        ];
    }

    private function dateRanges(array $dates): string
    {
        $dates = array_values(array_unique($dates));
        sort($dates);
        $ranges = [];
        $start = $end = null;
        foreach ($dates as $date) {
            if ($end !== null && Carbon::parse($end)->addDay()->toDateString() !== $date) {
                $ranges[] = $this->rangeLabel($start, $end);
                $start = null;
            }
            $start ??= $date;
            $end = $date;
        }
        if ($start !== null) $ranges[] = $this->rangeLabel($start, $end);
        return implode(', ', $ranges);
    }

    private function rangeLabel(string $start, string $end): string
    {
        $from = Carbon::parse($start)->format('d/m/Y');
        return $start === $end ? 'el '.$from : 'del '.$from.' al '.Carbon::parse($end)->format('d/m/Y');
    }

    private function serviceDates(array $definition): array
    {
        $dates = [];
        $weekdays = array_map('intval', $definition['days_of_week']);
        foreach (CarbonPeriod::create($definition['start_date'], $definition['end_date']) as $date) {
            if (in_array($date->dayOfWeekIso, $weekdays, true)) $dates[] = $date->toDateString();
        }
        return $dates;
    }

    private function runsOn(ProgrammingSchedule $schedule, string $date): bool
    {
        $dateValue = Carbon::parse($date);
        return $date >= $schedule->start_date->toDateString()
            && $date <= $schedule->end_date->toDateString()
            && ! in_array($date, $schedule->excluded_dates ?? [], true)
            && in_array($dateValue->dayOfWeekIso, array_map('intval', $schedule->days_of_week ?? []), true);
    }

    private function slotFromSchedule(ProgrammingSchedule $schedule, string $date): array
    {
        return [
            'date' => $date,
            'group_id' => $schedule->personnel_group_id,
            'vehicle_id' => $schedule->vehicle_id,
            'crew' => array_values(array_unique(array_merge([(int) $schedule->driver_id], array_map('intval', $schedule->helper_ids ?? [])))),
            'start' => $schedule->shift_start_time,
            'end' => $schedule->shift_end_time,
            'label' => $schedule->group_name,
        ];
    }

    private function checkOverlap(array &$conflicts, array $candidate, array $other): void
    {
        if (! $this->timesOverlap($candidate['start'], $candidate['end'], $other['start'], $other['end'], (int) ($other['day_offset'] ?? 0))) return;

        if ((int) $candidate['vehicle_id'] === (int) $other['vehicle_id']) {
            $this->addConflict($conflicts, $candidate['date'].': el vehículo ya está asignado a «'.$other['label'].'».');
        }
        if (array_intersect($candidate['crew'], $other['crew']) !== []) {
            $this->addConflict($conflicts, $candidate['date'].': un integrante del grupo ya está programado en «'.$other['label'].'».');
        }
        if ($candidate['group_id'] !== null && (int) $candidate['group_id'] === (int) $other['group_id']) {
            $this->addConflict($conflicts, $candidate['date'].': el grupo «'.$candidate['label'].'» ya tiene otra programación.');
        }
    }

    private function timesOverlap(string $firstStart, string $firstEnd, string $secondStart, string $secondEnd, int $secondDayOffset): bool
    {
        $first = $this->minutes($firstStart);
        $firstEndValue = $this->minutes($firstEnd);
        if ($firstEndValue <= $first) $firstEndValue += 1440;

        $second = $this->minutes($secondStart) + ($secondDayOffset * 1440);
        $secondEndValue = $this->minutes($secondEnd) + ($secondDayOffset * 1440);
        if ($secondEndValue <= $second) $secondEndValue += 1440;

        return $first < $secondEndValue && $second < $firstEndValue;
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $hours * 60 + $minutes;
    }

    private function suggestionsFor(array $conflicts, array $warnings): array
    {
        $rules = [
            'rango no contiene días seleccionados' => 'Amplíe el rango de fechas o marque al menos un día incluido en el periodo.',
            'feriado activo' => 'Cambie el rango de fechas o quite el día del feriado de los días de trabajo.',
            'vacaciones aprobadas' => 'Reemplace a la persona con vacaciones aprobadas por otra del mismo tipo que esté disponible.',
            'inasistencia' => 'Reemplace al integrante que registra la inasistencia por otra persona disponible del mismo tipo.',
            'un integrante del grupo ya está programado' => 'Cambie el turno o los días, o seleccione otro integrante para evitar el cruce.',
            'el vehículo ya está asignado' => 'Seleccione otro vehículo o ajuste los días o el turno de una de las programaciones.',
            'ya tiene otra programación' => 'Revise las fechas y los días de la otra programación de este grupo.',
        ];

        $suggestions = [];
        $conflictText = mb_strtolower(implode(' ', $conflicts));
        foreach ($rules as $pattern => $suggestion) {
            if (str_contains($conflictText, $pattern)) $suggestions[] = $suggestion;
        }

        if ($warnings !== []) {
            $suggestions[] = 'Consulte con el trabajador el estado de su solicitud pendiente. Si se aprueba, vuelva a validar la programación y ajuste las fechas o el personal si coincide.';
        }

        if ($conflicts !== [] && $suggestions === []) {
            $suggestions[] = 'Corrija los datos indicados y vuelva a validar la disponibilidad.';
        }

        return array_values(array_unique($suggestions));
    }

    private function addConflict(array &$conflicts, string $message): void
    {
        if (count($conflicts) < 100) $conflicts[] = $message;
    }
}
