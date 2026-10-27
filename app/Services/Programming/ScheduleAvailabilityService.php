<?php

namespace App\Services\Programming;

use App\Models\Employees\Employee;
use App\Models\Employees\Vacation;
use App\Models\Programming\Holiday;
use App\Models\Programming\ProgrammingSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleAvailabilityService
{
    public function check(array $definitions, ?int $excludeScheduleId = null): array
    {
        $employeeIds = collect($definitions)
            ->flatMap(fn ($item) => array_merge([$item['driver_id']], $item['helper_ids']))
            ->unique()->values();
        $minDate = collect($definitions)->min('start_date');
        $maxDate = collect($definitions)->max('end_date');

        $holidays = Holiday::active()->whereBetween('date', [$minDate, $maxDate])
            ->pluck('description', 'date')
            ->mapWithKeys(fn ($description, $date) => [Carbon::parse($date)->toDateString() => $description]);

        $employeeNames = Employee::query()->whereIn('id', $employeeIds)
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $vacations = Vacation::query()->with('employee:id,first_name,last_name')
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
            ->when($excludeScheduleId, fn ($query) => $query->where('id', '!=', $excludeScheduleId))
            ->get();

        $conflicts = [];
        $warnings = [];
        $plannedSlots = [];
        $replacementTargets = [];
        $blockedByDefinition = [];
        $hardConflicts = [];
        $serviceDays = 0;
        $availableServiceDays = 0;

        foreach ($definitions as $definitionIndex => $definition) {
            $dates = $this->serviceDates($definition);
            if ($dates === []) {
                $message = 'El rango no contiene días seleccionados para «'.$definition['group_name'].'».';
                $hardConflicts[] = $message;
                $this->addConflict($conflicts, $message);
                continue;
            }

            foreach ($dates as $date) {
                $serviceDays++;
                $dateConflicts = [];
                $dateWarnings = [];
                $crew = array_values(array_unique(array_merge([$definition['driver_id']], $definition['helper_ids'])));

                if ($holidays->has($date)) {
                    $this->addConflict($dateConflicts, $date.': feriado activo «'.$holidays->get($date).'».');
                }

                foreach ($vacations as $vacation) {
                    if (! in_array((int) $vacation->employee_id, $crew, true)) continue;
                    if ($date < $vacation->start_date->toDateString() || $date > $vacation->end_date->toDateString()) continue;

                    $employeeName = $vacation->employee?->full_name ?? 'Un integrante del grupo';
                    if ($vacation->status === 'Pendiente') {
                        $this->addWarning($dateWarnings, $date.': '.$employeeName.' tiene una solicitud de vacaciones pendiente. Puede guardar la programación, pero revise la solicitud antes de aprobarla.');
                    } elseif ($vacation->status === 'Aprobada') {
                        $this->addConflict($dateConflicts, $date.': '.$employeeName.' tiene vacaciones aprobadas.');
                        $this->addReplacementTarget($replacementTargets, (int) $vacation->employee_id, $definition, $employeeNames);
                    }
                }

                foreach ($absences->get($date, collect()) as $absence) {
                    if (in_array((int) $absence->employee_id, $crew, true)) {
                        $this->addConflict($dateConflicts, $date.': un integrante del grupo registra una inasistencia.');
                        $this->addReplacementTarget($replacementTargets, (int) $absence->employee_id, $definition, $employeeNames);
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
                        if (! $this->runsOn($other, $otherDate)) continue;
                        $otherSlot = $this->slotFromSchedule($other, $otherDate);
                        $otherSlot['day_offset'] = $dayOffset;
                        if ($this->timesOverlap($slot['start'], $slot['end'], $otherSlot['start'], $otherSlot['end'], $dayOffset)) {
                            foreach (array_intersect($slot['crew'], $otherSlot['crew']) as $employeeId) {
                                $this->addReplacementTarget($replacementTargets, (int) $employeeId, $definition, $employeeNames);
                            }
                        }
                        $this->checkOverlap($dateConflicts, $slot, $otherSlot);
                    }
                }
                foreach ($plannedSlots as $otherSlot) {
                    $dayDifference = (int) ((strtotime($otherSlot['date']) - strtotime($date)) / 86400);
                    if (abs($dayDifference) <= 1) {
                        $otherSlot['day_offset'] = $dayDifference;
                        if ($this->timesOverlap($slot['start'], $slot['end'], $otherSlot['start'], $otherSlot['end'], $dayDifference)) {
                            foreach (array_intersect($slot['crew'], $otherSlot['crew']) as $employeeId) {
                                $this->addReplacementTarget($replacementTargets, (int) $employeeId, $definition, $employeeNames);
                            }
                        }
                        $this->checkOverlap($dateConflicts, $slot, $otherSlot);
                    }
                }

                if ($dateConflicts !== []) {
                    $blockedByDefinition[$definitionIndex][$date] = array_values(array_unique(array_map(
                        fn ($message) => preg_replace('/^\d{4}-\d{2}-\d{2}:\s*/u', '', $message),
                        $dateConflicts
                    )));
                    foreach ($dateConflicts as $conflict) $this->addConflict($conflicts, $conflict);
                    continue;
                }

                $availableServiceDays++;
                foreach ($dateWarnings as $warning) $this->addWarning($warnings, $warning);
                $plannedSlots[] = $slot;
            }
        }

        $blockedDates = [];
        $skipped = [];
        $skippedServiceDays = 0;
        foreach ($blockedByDefinition as $definitionIndex => $dateReasons) {
            $dates = array_keys($dateReasons);
            sort($dates);
            $blockedDates[$definitionIndex] = $dates;
            $skippedServiceDays += count($dates);
            $reasonDates = [];
            foreach ($dateReasons as $date => $reasons) {
                foreach ($reasons as $reason) $reasonDates[$reason][] = $date;
            }
            foreach ($reasonDates as $reason => $reasonDatesList) {
                $skipped[] = [
                    'group_name' => $definitions[$definitionIndex]['group_name'],
                    'reason' => $reason,
                    'dates' => $this->formatDateRanges($reasonDatesList),
                ];
            }
        }

        $conflicts = array_slice($this->groupRepeatedDateMessages(array_values(array_unique($conflicts))), 0, 100);
        $warnings = array_slice($this->groupRepeatedDateMessages(array_values(array_unique($warnings))), 0, 100);
        ksort($blockedDates);

        $suggestions = $skipped === []
            ? array_values(array_unique(array_merge(
                $this->suggestionsFor($conflicts, $warnings),
                $this->replacementSuggestions($replacementTargets, $existing, $plannedSlots)
            )))
            : [];
        if ($hardConflicts !== [] && $suggestions === []) {
            $suggestions[] = 'Corrija los datos indicados y vuelva a validar la disponibilidad.';
        }

        return [
            'conflicts' => $conflicts,
            'hard_conflicts' => $hardConflicts,
            'blocked_dates' => $blockedDates,
            'skipped' => $skipped,
            'skipped_service_days' => $skippedServiceDays,
            'available_service_days' => $availableServiceDays,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'service_days' => $serviceDays,
        ];
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

        if ($candidate['vehicle_id'] === $other['vehicle_id']) {
            $this->addConflict($conflicts, $candidate['date'].': el vehículo ya está asignado a «'.$other['label'].'».');
        }
        if (array_intersect($candidate['crew'], $other['crew']) !== []) {
            $this->addConflict($conflicts, $candidate['date'].': un integrante del grupo ya está programado en «'.$other['label'].'».');
        }
        if ($candidate['group_id'] !== null && $candidate['group_id'] === $other['group_id']) {
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
            'vacaciones aprobadas' => null,
            'inasistencia' => null,
            'un integrante del grupo ya está programado' => 'Cambie el turno o los días, o seleccione otro integrante para evitar el cruce.',
            'el vehículo ya está asignado' => 'Seleccione otro vehículo o ajuste los días o el turno de una de las programaciones.',
            'ya tiene otra programación' => 'Revise las fechas y los días de la otra programación de este grupo.',
        ];

        $suggestions = [];
        $conflictText = mb_strtolower(implode(' ', $conflicts));
        foreach ($rules as $pattern => $suggestion) {
            if ($suggestion !== null && str_contains($conflictText, $pattern)) $suggestions[] = $suggestion;
        }

        if ($warnings !== []) {
            $suggestions[] = 'Consulte con el trabajador el estado de su solicitud pendiente. Si se aprueba, vuelva a validar la programación y ajuste las fechas o el personal si coincide.';
        }

        return array_values(array_unique($suggestions));
    }

    private function addReplacementTarget(array &$targets, int $employeeId, array $definition, $employeeNames): void
    {
        $driverId = (int) $definition['driver_id'];
        $helperIds = array_map('intval', $definition['helper_ids']);
        $role = $employeeId === $driverId ? 'Conductor' : (in_array($employeeId, $helperIds, true) ? 'Ayudante' : null);
        if ($role === null) return;

        $employee = $employeeNames->get($employeeId);
        $name = $employee ? trim($employee->first_name.' '.$employee->last_name) : 'El integrante';
        $key = $definition['group_id'].'|'.$employeeId.'|'.$role;
        $targets[$key] = [
            'employee_id' => $employeeId,
            'employee_name' => $name,
            'role' => $role,
            'definition' => $definition,
        ];
    }

    private function replacementSuggestions(array $targets, $existing, array $plannedSlots): array
    {
        $suggestions = [];
        foreach ($targets as $target) {
            $definition = $target['definition'];
            $dates = $this->serviceDates($definition);
            if ($dates === []) continue;
            $firstDate = $dates[0];
            $lastDate = end($dates);
            $role = $target['role'];
            $crewIds = array_values(array_unique(array_merge([(int) $definition['driver_id']], array_map('intval', $definition['helper_ids']))));

            $candidates = Employee::query()->where('status', true)
                ->whereHas('employeeType', fn ($query) => $query->where('name', $role))
                ->whereHas('contracts', function ($query) use ($firstDate, $lastDate) {
                    $query->whereIn('contract_type', ['Permanente', 'Nombrado'])
                        ->where('is_active', true)
                        ->whereDate('start_date', '<=', $firstDate)
                        ->where(function ($endQuery) use ($lastDate) {
                            $endQuery->whereNull('end_date')->orWhereDate('end_date', '>=', $lastDate);
                        });
                })
                ->whereNotIn('id', $crewIds)
                ->orderBy('last_name')->orderBy('first_name')
                ->limit(100)->get();

            $availableCandidates = [];
            foreach ($candidates as $candidate) {
                if ($this->replacementIsAvailable($candidate->id, $definition, $dates, $existing, $plannedSlots)) {
                    $availableCandidates[] = $candidate->full_name;
                }
                if (count($availableCandidates) >= 3) break;
            }

            if ($availableCandidates !== []) {
                $suggestions[] = 'Sugerencia: Reemplazar a '.$target['employee_name'].' ('.$role.') por '.implode(', ', $availableCandidates).'.';
            } else {
                $suggestions[] = 'No se encontró un reemplazo disponible de tipo '.$role.' para '.$target['employee_name'].' en todas las fechas y turnos seleccionados.';
            }
        }

        return array_values(array_unique($suggestions));
    }

    private function replacementIsAvailable(int $employeeId, array $definition, array $dates, $existing, array $plannedSlots): bool
    {
        $firstDate = $dates[0];
        $lastDate = end($dates);
        $vacations = Vacation::query()->where('employee_id', $employeeId)
            ->whereIn('status', ['Pendiente', 'Aprobada'])
            ->whereDate('start_date', '<=', $lastDate)
            ->whereDate('end_date', '>=', $firstDate)
            ->get(['start_date', 'end_date']);

        foreach ($dates as $date) {
            foreach ($vacations as $vacation) {
                if ($date >= $vacation->start_date->toDateString() && $date <= $vacation->end_date->toDateString()) return false;
            }
        }

        if (Schema::hasTable('attendances')) {
            $absenceExists = DB::table('attendances')->where('employee_id', $employeeId)
                ->whereIn('status', ['absent'])
                ->whereBetween('date', [$firstDate, $lastDate])
                ->whereIn('date', $dates)->exists();
            if ($absenceExists) return false;
        }

        foreach ($dates as $date) {
            foreach ($existing as $schedule) {
                foreach ([-1, 0, 1] as $dayOffset) {
                    $otherDate = Carbon::parse($date)->addDays($dayOffset)->toDateString();
                    if (! $this->runsOn($schedule, $otherDate)) continue;
                    $otherCrew = array_values(array_unique(array_merge([(int) $schedule->driver_id], array_map('intval', $schedule->helper_ids ?? []))));
                    if (! in_array($employeeId, $otherCrew, true)) continue;
                    if ($this->timesOverlap($definition['shift_start_time'], $definition['shift_end_time'], $schedule->shift_start_time, $schedule->shift_end_time, $dayOffset)) return false;
                }
            }

            foreach ($plannedSlots as $slot) {
                $dayDifference = (int) ((strtotime($slot['date']) - strtotime($date)) / 86400);
                if (abs($dayDifference) > 1 || ! in_array($employeeId, $slot['crew'], true)) continue;
                if ($this->timesOverlap($definition['shift_start_time'], $definition['shift_end_time'], $slot['start'], $slot['end'], $dayDifference)) return false;
            }
        }

        return true;
    }

    private function groupRepeatedDateMessages(array $messages): array
    {
        $items = [];
        $groups = [];
        $order = 0;

        foreach ($messages as $message) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2}):\s*(.+)$/u', $message, $matches)) {
                $text = $matches[2];
                $key = $text;
                if (! isset($groups[$key])) {
                    $groups[$key] = ['order' => $order++, 'dates' => []];
                }
                $groups[$key]['dates'][] = $matches[1];
                continue;
            }

            $items[] = ['order' => $order++, 'message' => $message];
        }

        foreach ($groups as $text => $group) {
            $dates = array_values(array_unique($group['dates']));
            sort($dates);
            if (count($dates) === 1) {
                $message = $dates[0].': '.$text;
            } else {
                $formattedDates = $this->formatDateRanges($dates);
                $message = rtrim($text, '.').' (fechas afectadas: '.implode(', ', $formattedDates).').';
            }
            $items[] = ['order' => $group['order'], 'message' => $message];
        }

        usort($items, fn ($first, $second) => $first['order'] <=> $second['order']);

        return array_column($items, 'message');
    }

    private function formatDateRanges(array $dates): array
    {
        $dates = array_values(array_unique($dates));
        sort($dates);
        if ($dates === []) return [];

        $ranges = [];
        $start = Carbon::parse($dates[0]);
        $end = $start->copy();

        foreach (array_slice($dates, 1) as $date) {
            $current = Carbon::parse($date);
            if ($current->isSameDay($end->copy()->addDay())) {
                $end = $current;
                continue;
            }

            $ranges[] = $this->formatDateRange($start, $end);
            $start = $current;
            $end = $current->copy();
        }

        $ranges[] = $this->formatDateRange($start, $end);

        return $ranges;
    }

    private function formatDateRange(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) return $start->format('d/m/Y');
        if ($start->isSameMonth($end)) return $start->format('d').' al '.$end->format('d/m/Y');
        if ($start->isSameYear($end)) return $start->format('d/m').' al '.$end->format('d/m/Y');

        return $start->format('d/m/Y').' al '.$end->format('d/m/Y');
    }

    private function addConflict(array &$conflicts, string $message): void
    {
        $conflicts[] = $message;
    }

    private function addWarning(array &$warnings, string $message): void
    {
        $warnings[] = $message;
    }
}
