<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Programming\Holiday;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ProgrammingScheduleHistory;
use App\Models\Programming\Shift;
use App\Models\Vehicle;
use App\Models\Programming\Zone;
use App\Services\Programming\ScheduleAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ProgrammingScheduleController extends Controller
{
    public function __construct(private ScheduleAvailabilityService $availability) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ProgrammingSchedule::query()->select('programming_schedules.*');
            $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('end_date', '>=', $request->input('date_from')));
            $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('start_date', '<=', $request->input('date_to')));
            $query->when($request->filled('zone_id'), fn ($q) => $q->where('zone_id', $request->input('zone_id')));
            $query->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->input('shift_id')));

            $schedules = $query->latest()->get();
            $rows = $schedules->flatMap(function ($schedule) use ($request) {
                $from = $request->filled('date_from')
                    ? max($schedule->start_date->toDateString(), Carbon::parse($request->input('date_from'))->toDateString())
                    : $schedule->start_date->toDateString();
                $to = $request->filled('date_to')
                    ? min($schedule->end_date->toDateString(), Carbon::parse($request->input('date_to'))->toDateString())
                    : $schedule->end_date->toDateString();
                if ($from > $to) return collect();

                $weekdays = array_map('intval', $schedule->days_of_week ?? []);
                $rows = collect();
                foreach (CarbonPeriod::create($from, $to) as $date) {
                    if (! in_array($date->dayOfWeekIso, $weekdays, true)) continue;
                    $serviceDate = $date->toDateString();
                    $actions = view('admin.programming.schedules.template.actions', compact('schedule', 'serviceDate'))->render();

                    $rows->push([
                        'date' => $date->format('d/m/Y'),
                        'date_sort' => $date->toDateString(),
                        'status_badge' => $schedule->status === 'Finalizada'
                            ? '<span class="badge schedule-status schedule-status-finished">Finalizada</span>'
                            : ($schedule->status === 'Reprogramado'
                                ? '<span class="badge schedule-status schedule-status-rescheduled">Reprogramada</span>'
                                : '<span class="badge schedule-status schedule-status-planned">Programada</span>'),
                        'zone_name' => $schedule->zone_name,
                        'shift_html' => '<span class="badge schedule-shift">'.e($schedule->shift_name).'</span>',
                        'vehicle_html' => '<span class="schedule-vehicle">'.e($schedule->vehicle_label).'</span>',
                        'driver_name' => $schedule->driver_name,
                        'helpers_html' => collect($schedule->helper_names ?? [])->map(fn ($name) => '<span class="schedule-helper">'.e($name).'</span>')->implode(' '),
                        'actions' => $actions,
                    ]);
                }

                return $rows;
            })->values();

            return DataTables::of($rows)
                ->rawColumns(['status_badge', 'shift_html', 'vehicle_html', 'helpers_html', 'actions'])
                ->make(true);
        }

        $zones = Zone::query()->orderBy('name')->get(['id', 'name']);
        $shifts = Shift::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.programming.schedules.index', compact('zones', 'shifts'));
    }

    public function create()
    {
        return view('admin.programming.schedules.create', $this->formData());
    }

    public function createMass()
    {
        return view('admin.programming.schedules.create', array_merge($this->formData(), ['mode' => 'mass']));
    }

    public function groupData(PersonnelGroup $personnelGroup)
    {
        if (! $personnelGroup->status) {
            return response()->json(['message' => 'El grupo está inactivo.'], 409);
        }

        $personnelGroup->load(['shift', 'zone', 'vehicle', 'helpers']);

        return response()->json([
            'group_id' => $personnelGroup->id,
            'group_name' => $personnelGroup->name,
            'shift_id' => $personnelGroup->shift_id,
            'vehicle_id' => $personnelGroup->vehicle_id,
            'driver_id' => $personnelGroup->driver_id,
            'helper_ids' => $personnelGroup->helpers->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'days_of_week' => array_map('intval', $personnelGroup->days_of_week ?? []),
            'shift' => $personnelGroup->shift?->name,
            'shift_label' => $personnelGroup->shift
                ? $personnelGroup->shift->name.' ('.substr($personnelGroup->shift->start_time, 0, 5).'–'.substr($personnelGroup->shift->end_time, 0, 5).')'
                : '—',
            'zone' => $personnelGroup->zone?->name,
            'vehicle' => $personnelGroup->vehicle
                ? trim($personnelGroup->vehicle->plate.' · '.$personnelGroup->vehicle->name)
                : '—',
        ]);
    }

    public function holidays(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json(Holiday::active()
            ->whereBetween('date', [$data['start_date'], $data['end_date']])
            ->orderBy('date')->get(['id', 'date', 'description']));
    }

    public function validateAvailability(Request $request)
    {
        $definitions = $this->definitions($request);
        $excludeScheduleId = $request->filled('schedule_id') ? $request->integer('schedule_id') : null;
        if ($excludeScheduleId) {
            ProgrammingSchedule::query()->whereIn('status', ['Programada', 'Reprogramado'])->findOrFail($excludeScheduleId);
        }
        $result = $this->availability->check($definitions, $excludeScheduleId);
        $crewAvailability = [];
        foreach ($definitions as $definitionIndex => $definition) {
            foreach ($result['crew_availability'][$definitionIndex] ?? [] as $employeeId => $issues) {
                $crewAvailability[$definition['group_id']][(string) $employeeId] = $issues;
            }
        }
        $hardErrors = $result['hard_conflicts'];
        if ($result['available_service_days'] === 0 && $hardErrors === []) {
            $hardErrors[] = 'Ninguna fecha del periodo está disponible para programar. Revise las fechas omitidas.';
        }

        if ($hardErrors !== []) {
            return response()->json([
                'available' => false,
                'conflicts' => $hardErrors,
                'skipped' => $result['skipped'],
                'warnings' => $result['warnings'],
                'crew_availability' => $crewAvailability,
                'group_results' => $result['group_results'],
                'service_days' => $result['service_days'],
                'available_service_days' => $result['available_service_days'],
                'skipped_service_days' => $result['skipped_service_days'],
            ], 409);
        }

        return response()->json([
            'available' => true,
            'token' => $this->availabilityToken($definitions, $result['blocked_dates']),
            'service_days' => $result['service_days'],
            'available_service_days' => $result['available_service_days'],
            'skipped_service_days' => $result['skipped_service_days'],
            'program_count' => count($definitions),
            'skipped' => $result['skipped'],
            'warnings' => $result['warnings'],
            'crew_availability' => $crewAvailability,
            'group_results' => $result['group_results'],
            'suggestions' => $result['suggestions'],
            'message' => 'Se programarán '.$result['available_service_days'].' fechas disponibles y se omitirán '.$result['skipped_service_days'].' por inconsistencias.',
        ]);
    }

    public function store(Request $request)
    {
        $definitions = $this->definitions($request);
        $result = $this->availability->check($definitions);
        $this->assertAvailabilityToken($request, $definitions, $result['blocked_dates']);
        $hardErrors = $result['hard_conflicts'];
        if ($result['available_service_days'] === 0 && $hardErrors === []) {
            $hardErrors[] = 'Ninguna fecha del periodo está disponible para programar. Revise las fechas omitidas.';
        }
        if ($hardErrors !== []) {
            return response()->json(['available' => false, 'conflicts' => $hardErrors, 'skipped' => $result['skipped'], 'warnings' => $result['warnings']], 409);
        }

        $scheduleDefinitions = [];
        foreach ($definitions as $index => $definition) {
            $scheduleDefinitions = array_merge($scheduleDefinitions, $this->availableSegments($definition, $result['blocked_dates'][$index] ?? []));
        }
        $isMass = $request->input('mode') === 'mass';
        $batchUuid = ($isMass || count($scheduleDefinitions) > 1) ? (string) Str::uuid() : null;
        $created = DB::transaction(function () use ($scheduleDefinitions, $batchUuid, $isMass) {
            return collect($scheduleDefinitions)->map(function ($definition) use ($batchUuid, $isMass) {
                $schedule = ProgrammingSchedule::create($this->attributes($definition, $batchUuid));
                $this->recordHistory($schedule, $isMass ? 'created_mass' : ($batchUuid ? 'created_mass' : 'created'), null, $this->snapshot($schedule), $isMass ? 'Creada en programación masiva para fechas disponibles.' : ($batchUuid ? 'Programación creada para fechas disponibles.' : 'Programación creada.'));
                return $schedule;
            });
        });

        $message = 'Se generaron programaciones para '.$result['available_service_days'].' fechas disponibles.';
        if ($result['skipped_service_days'] > 0) $message .= ' Se omitieron '.$result['skipped_service_days'].' fechas por las inconsistencias indicadas.';

        return response()->json([
            'message' => $message,
            'created_schedules' => count($created),
            'skipped' => $result['skipped'],
            'warnings' => $result['warnings'],
        ]);
    }

    public function edit(Request $request, ProgrammingSchedule $schedule)
    {
        if (! in_array($schedule->status, ['Programada', 'Reprogramado'], true)) {
            return response('Solo se pueden modificar programaciones activas.', 409);
        }

        $data = $request->validate(['date' => ['required', 'date']]);
        $serviceDate = Carbon::parse($data['date'])->toDateString();
        if ($serviceDate < $schedule->start_date->toDateString() || $serviceDate > $schedule->end_date->toDateString()
            || ! in_array(Carbon::parse($serviceDate)->dayOfWeekIso, array_map('intval', $schedule->days_of_week ?? []), true)) {
            return response('La fecha seleccionada no pertenece a esta programación.', 422);
        }

        return view('admin.programming.schedules.edit-day', $this->formData($schedule, $serviceDate));
    }

    public function update(Request $request, ProgrammingSchedule $schedule)
    {
        if (! in_array($schedule->status, ['Programada', 'Reprogramado'], true)) {
            return response()->json(['message' => 'Solo se pueden modificar programaciones activas.'], 409);
        }

        $dayData = $request->validate([
            'service_date' => ['required', 'date'],
            'change_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'service_date.required' => 'No se recibió la fecha de la programación que se va a modificar.',
            'change_reason.required' => 'Indique el motivo del cambio.',
            'change_reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'change_reason.max' => 'El motivo no debe superar los 500 caracteres.',
        ]);
        $serviceDate = Carbon::parse($dayData['service_date'])->toDateString();
        if ($serviceDate < $schedule->start_date->toDateString() || $serviceDate > $schedule->end_date->toDateString()
            || ! in_array(Carbon::parse($serviceDate)->dayOfWeekIso, array_map('intval', $schedule->days_of_week ?? []), true)) {
            throw ValidationException::withMessages(['service_date' => 'La fecha ya no pertenece a esta programación. Actualice el listado e inténtelo nuevamente.']);
        }

        if ($request->input('mode') !== 'individual') {
            throw ValidationException::withMessages(['mode' => 'La edición por fecha es individual.']);
        }
        $definitions = $this->definitions($request, $schedule->id);
        if (count($definitions) !== 1) {
            throw ValidationException::withMessages(['mode' => 'Solo se puede modificar una programación por fecha.']);
        }
        $definitions[0]['start_date'] = $serviceDate;
        $definitions[0]['end_date'] = $serviceDate;
        $definitions[0]['days_of_week'] = [Carbon::parse($serviceDate)->dayOfWeekIso];
        $result = $this->availability->check($definitions, $schedule->id);
        $this->assertAvailabilityToken($request, $definitions, $result['blocked_dates']);
        $hardErrors = $result['hard_conflicts'];
        if ($result['available_service_days'] === 0 && $hardErrors === []) {
            $hardErrors[] = 'La fecha no está disponible para reprogramar. Revise las inconsistencias.';
        }
        if ($hardErrors !== []) {
            return response()->json(['available' => false, 'conflicts' => $hardErrors, 'skipped' => $result['skipped'], 'warnings' => $result['warnings']], 409);
        }

        $reason = trim($dayData['change_reason']);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['change_reason' => 'El motivo debe tener al menos 5 caracteres útiles.']);
        }
        $newDefinition = $definitions[0];
        $newDefinition['start_date'] = $serviceDate;
        $newDefinition['end_date'] = $serviceDate;
        $newDefinition['days_of_week'] = [Carbon::parse($serviceDate)->dayOfWeekIso];
        $newDefinition['status'] = 'Reprogramado';

        $oldDefinition = [
            'group_id' => $schedule->personnel_group_id,
            'group_name' => $schedule->group_name,
            'start_date' => $schedule->start_date->toDateString(),
            'end_date' => $schedule->end_date->toDateString(),
            'shift_id' => $schedule->shift_id,
            'shift_name' => $schedule->shift_name,
            'shift_start_time' => $schedule->shift_start_time,
            'shift_end_time' => $schedule->shift_end_time,
            'zone_id' => $schedule->zone_id,
            'zone_name' => $schedule->zone_name,
            'vehicle_id' => $schedule->vehicle_id,
            'vehicle_label' => $schedule->vehicle_label,
            'driver_id' => $schedule->driver_id,
            'driver_name' => $schedule->driver_name,
            'helper_ids' => $schedule->helper_ids ?? [],
            'helper_names' => $schedule->helper_names ?? [],
            'days_of_week' => $schedule->days_of_week ?? [],
        ];
        $beforeDay = $this->snapshot($schedule);
        $beforeDay['periodo'] = Carbon::parse($serviceDate)->format('d/m/Y').' - '.Carbon::parse($serviceDate)->format('d/m/Y');
        $beforeDay['dias'] = PersonnelGroup::WEEKDAYS[Carbon::parse($serviceDate)->dayOfWeekIso] ?? '';
        $remainingSegments = $this->availableSegments($oldDefinition, [$serviceDate]);
        $batchUuid = $schedule->batch_uuid ?: (count($remainingSegments) > 1 ? (string) Str::uuid() : null);

        DB::transaction(function () use ($schedule, $remainingSegments, $newDefinition, $batchUuid, $reason, $beforeDay) {
            $before = $this->snapshot($schedule);
            if ($remainingSegments === []) {
                $attributes = $this->attributes($newDefinition, $schedule->batch_uuid);
                unset($attributes['created_by']);
                $schedule->update($attributes);
                $this->recordHistory($schedule, 'reprogrammed', $beforeDay, $this->snapshot($schedule), 'Motivo: '.$reason);
                return;
            }

            $first = array_shift($remainingSegments);
            $attributes = $this->attributes($first, $batchUuid);
            unset($attributes['created_by']);
            $schedule->update($attributes);
            $this->recordHistory($schedule, 'split', $before, $this->snapshot($schedule), 'Se separó la fecha modificada. Motivo: '.$reason);

            foreach ($remainingSegments as $segment) {
                $segmentSchedule = ProgrammingSchedule::create($this->attributes($segment, $batchUuid));
                $this->recordHistory($segmentSchedule, 'split_segment', null, $this->snapshot($segmentSchedule), 'Segmento restante de la programación original.');
            }

            $reprogrammed = ProgrammingSchedule::create($this->attributes($newDefinition, $batchUuid));
            $this->recordHistory($reprogrammed, 'reprogrammed', $beforeDay, $this->snapshot($reprogrammed), 'Motivo: '.$reason);
        });

        return response()->json([
            'message' => 'La programación del '.$serviceDate.' fue reprogramada. Motivo registrado en el historial.',
            'warnings' => $result['warnings'],
        ]);
    }

    public function destroy(ProgrammingSchedule $schedule)
    {
        if (! in_array($schedule->status, ['Programada', 'Reprogramado'], true)) {
            return response()->json(['message' => 'Solo se pueden eliminar programaciones activas.'], 409);
        }

        DB::transaction(function () use ($schedule) {
            $this->recordHistory($schedule, 'deleted', $this->snapshot($schedule), null, 'Programación eliminada.');
            $schedule->delete();
        });

        return response()->json(['message' => 'Programación eliminada exitosamente.']);
    }

    public function finish(ProgrammingSchedule $schedule)
    {
        if (! in_array($schedule->status, ['Programada', 'Reprogramado'], true)) {
            return response()->json(['message' => 'La programación ya fue finalizada.'], 409);
        }

        DB::transaction(function () use ($schedule) {
            $before = $this->snapshot($schedule);
            $schedule->update(['status' => 'Finalizada', 'finished_at' => now()]);
            $this->recordHistory($schedule, 'finished', $before, $this->snapshot($schedule), 'Programación finalizada.');
        });

        return response()->json(['message' => 'Programación finalizada exitosamente.']);
    }

    public function history(ProgrammingSchedule $schedule)
    {
        $histories = $schedule->histories()->with('actor')->latest()->get();
        $labelPattern = addcslashes($schedule->group_name.' · ', '\\%_').'%';
        $splitHistories = ProgrammingScheduleHistory::query()
            ->where('action', 'split')
            ->where('schedule_label', 'like', $labelPattern)
            ->latest()->get();
        $historyRows = $this->scheduleHistoryRows($histories, $splitHistories, $schedule);
        return view('admin.programming.schedules.history-detail', compact('schedule', 'historyRows'));
    }
    public function allHistory()
    {
        $schedule = null;
        $histories = ProgrammingScheduleHistory::with('actor')->latest()->limit(250)->get();

        return view('admin.programming.schedules.history', compact('schedule', 'histories'));
    }

    public function groupDetails(PersonnelGroup $personnelGroup)
    {
        $personnelGroup->load(['shift', 'zone', 'vehicle', 'driver', 'helpers']);
        $schedules = ProgrammingSchedule::query()->where('personnel_group_id', $personnelGroup->id)->latest()->get();
        $scheduleIds = $schedules->pluck('id');
        $labelPattern = addcslashes($personnelGroup->name.' · ', '\\%_').'%';
        $histories = ProgrammingScheduleHistory::query()->with('actor')
            ->where(function ($query) use ($scheduleIds, $labelPattern) {
                if ($scheduleIds->isNotEmpty()) $query->whereIn('programming_schedule_id', $scheduleIds);
                else $query->whereRaw('1 = 0');
                $query->orWhere('schedule_label', 'like', $labelPattern);
            })->latest()->get();

        return view('admin.programming.personnel-groups.details', compact('personnelGroup', 'schedules', 'histories'));
    }

    private function scheduleHistoryRows($histories, $splitHistories = null, ?ProgrammingSchedule $schedule = null)
    {
        $rows = collect();
        $splitHistories ??= collect();
        $fields = [
            'turno' => ['Cambio de turno', 'Cambio de turno'],
            'vehiculo' => ['Cambio de vehículo', 'Cambio de vehículo'],
            'conductor' => ['Cambio de conductor', 'Cambio de conductor'],
        ];

        foreach ($histories->whereIn('action', ['reprogrammed', 'updated']) as $history) {
            $before = $history->before_data ?? [];
            $after = $history->after_data ?? [];
            $reasonText = preg_replace('/^Motivo:\s*/u', '', (string) $history->notes);
            $reasonParts = preg_split('/\s*\|\s*(?=Cambio de )/u', $reasonText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($before === [] && $schedule) {
                $splitHistory = $splitHistories->first(function ($candidate) use ($reasonText, $schedule) {
                    $candidateReason = preg_replace('/^.*?Motivo:\s*/u', '', (string) $candidate->notes);
                    if ($candidateReason !== $reasonText) return false;

                    $period = $candidate->before_data['periodo'] ?? null;
                    if (! $period || ! preg_match('/^(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})$/u', $period, $dates)) return false;

                    try {
                        $periodStart = Carbon::createFromFormat('d/m/Y', $dates[1])->toDateString();
                        $periodEnd = Carbon::createFromFormat('d/m/Y', $dates[2])->toDateString();
                    } catch (\Throwable) {
                        return false;
                    }

                    $serviceDate = $schedule->start_date->toDateString();
                    return $periodStart <= $serviceDate && $serviceDate <= $periodEnd;
                });
                $before = $splitHistory?->before_data ?? [];
            }

            foreach ($fields as $key => [$type, $reasonPrefix]) {
                if (! array_key_exists($key, $before) || ! array_key_exists($key, $after) || $before[$key] === $after[$key]) continue;
                $reason = collect($reasonParts)->first(fn ($part) => str_starts_with($part, $reasonPrefix));
                $rows->push([
                    'date' => $history->created_at?->timezone('America/Lima'),
                    'type' => $type,
                    'before' => $before[$key],
                    'after' => $after[$key],
                    'reason' => $this->cleanChangeReason($reason ?: $reasonText),
                ]);
            }

            if (array_key_exists('ayudantes', $before) && array_key_exists('ayudantes', $after) && $before['ayudantes'] !== $after['ayudantes']) {
                $previousHelpers = array_values(array_filter(array_map('trim', explode(',', (string) $before['ayudantes']))));
                $newHelpers = array_values(array_filter(array_map('trim', explode(',', (string) $after['ayudantes']))));
                $removedHelpers = array_values(array_diff($previousHelpers, $newHelpers));
                $addedHelpers = array_values(array_diff($newHelpers, $previousHelpers));
                $helperReasons = collect($reasonParts)->filter(fn ($part) => str_starts_with($part, 'Cambio de ayudante'))->values();

                foreach (array_unique(array_merge($removedHelpers, $addedHelpers)) as $index => $helperName) {
                    $rows->push([
                        'date' => $history->created_at?->timezone('America/Lima'),
                        'type' => 'Cambio de ayudante',
                        'before' => $removedHelpers[$index] ?? '—',
                        'after' => $addedHelpers[$index] ?? '—',
                        'reason' => $this->cleanChangeReason($helperReasons[$index] ?? $reasonText),
                    ]);
                }
            }
        }

        return $rows;
    }

    private function cleanChangeReason(string $reason): string
    {
        return trim(preg_replace('/^Cambio de (?:turno|vehículo|conductor|ayudante)(?:\s*\([^)]*\))?:\s*/u', '', $reason));
    }

    private function formData(?ProgrammingSchedule $schedule = null, ?string $singleDayDate = null): array
    {
        $groups = PersonnelGroup::with(['shift', 'zone', 'vehicle', 'driver', 'helpers'])
            ->where('status', true)->orderBy('name')->get();
        $drivers = $this->employeesOfType('Conductor')->get();
        $helpers = $this->employeesOfType('Ayudante')->get();

        if ($schedule) {
            $selectedDriver = Employee::find($schedule->driver_id);
            if ($selectedDriver && ! $drivers->contains('id', $selectedDriver->id)) $drivers->push($selectedDriver);
            $selectedHelpers = Employee::whereIn('id', $schedule->helper_ids ?? [])->get();
            $helpers = $helpers->merge($selectedHelpers)->unique('id');
            $group = $schedule->personnel_group_id
                ? PersonnelGroup::with(['shift', 'zone', 'vehicle', 'driver', 'helpers'])->find($schedule->personnel_group_id)
                : null;
            if ($group && ! $groups->contains('id', $group->id)) $groups->push($group);
        }

        return [
            'mode' => 'individual',
            'schedule' => $schedule,
            'groups' => $groups,
            'drivers' => $drivers,
            'helpers' => $helpers,
            'weekdays' => PersonnelGroup::WEEKDAYS,
            'shifts' => Shift::orderBy('start_time')->get(),
            'vehicles' => Vehicle::orderBy('plate')->get(),
            'singleDayDate' => $singleDayDate,
        ];
    }

    private function employeesOfType(string $type)
    {
        return Employee::with('employeeType')->where('status', true)
            ->whereHas('employeeType', fn ($query) => $query->where('name', $type))
            ->orderBy('last_name')->orderBy('first_name');
    }

    private function definitions(Request $request, ?int $excludeScheduleId = null): array
    {
        $mode = $request->input('mode', 'individual');
        $rules = [
            'mode' => ['required', Rule::in(['individual', 'mass'])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];

        if ($mode === 'mass') {
            $rules['group_ids'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['group_ids.*'] = ['required', 'integer', 'distinct', 'exists:personnel_groups,id'];
            $rules['groups'] = ['sometimes', 'array'];
            $rules['groups.*.driver_id'] = ['required', 'integer', 'exists:employees,id'];
            $rules['groups.*.helper_ids'] = ['required', 'array', 'min:1'];
            $rules['groups.*.helper_ids.*'] = ['required', 'integer', 'exists:employees,id'];
            $rules['holidays_configured'] = ['sometimes', 'boolean'];
            $rules['holiday_ids'] = ['sometimes', 'array'];
            $rules['holiday_ids.*'] = ['integer', 'distinct', 'exists:holidays,id'];
        } else {
            $rules['group_id'] = ['required', 'integer', 'exists:personnel_groups,id'];
            $rules['driver_id'] = ['required', 'integer', 'exists:employees,id'];
            $rules['helper_ids'] = ['required', 'array', 'min:1'];
            $rules['helper_ids.*'] = ['required', 'integer', 'distinct', 'exists:employees,id'];
            $rules['days_of_week'] = ['required', 'array', 'min:1'];
            $rules['days_of_week.*'] = ['required', 'integer', 'distinct', Rule::in(array_keys(PersonnelGroup::WEEKDAYS))];
            $rules['shift_id'] = ['sometimes', 'required', 'integer', 'exists:shifts,id'];
            $rules['vehicle_id'] = ['sometimes', 'required', 'integer', 'exists:vehicles,id'];
        }

        $data = $request->validate($rules);
        if ($mode === 'mass') {
            foreach ($data['group_ids'] as $groupId) {
                $helperIds = array_map('intval', $data['groups'][$groupId]['helper_ids'] ?? []);
                if (count($helperIds) !== count(array_unique($helperIds))) {
                    throw ValidationException::withMessages([
                        'groups.'.$groupId.'.helper_ids' => 'No repita un ayudante dentro del mismo grupo.',
                    ]);
                }
            }
        }

        $groupIds = $mode === 'mass'
            ? array_map('intval', $data['group_ids'])
            : [(int) $data['group_id']];
        $groups = PersonnelGroup::with(['shift', 'zone', 'vehicle', 'driver', 'helpers'])
            ->whereIn('id', $groupIds)->get()->keyBy('id');

        if ($groups->count() !== count($groupIds)) {
            throw ValidationException::withMessages(['group_id' => 'Uno de los grupos seleccionados ya no existe.']);
        }

        $definitions = [];
        foreach ($groupIds as $groupId) {
            $group = $groups->get($groupId);
            if (! $group->status) {
                throw ValidationException::withMessages(['group_id' => 'El grupo «'.$group->name.'» está inactivo.']);
            }
            if (! $group->shift || ! $group->zone || ! $group->vehicle || ! $group->driver) {
                throw ValidationException::withMessages(['group_id' => 'El grupo «'.$group->name.'» tiene datos incompletos. Edítelo antes de programar.']);
            }

            $override = $data['groups'][$groupId] ?? null;
            if ($mode === 'mass' && $override === null) {
                throw ValidationException::withMessages(['groups' => 'Revise el personal de todos los grupos seleccionados.']);
            }
            $driverId = $mode === 'individual'
                ? (int) $data['driver_id']
                : (int) ($override['driver_id'] ?? $group->driver_id);
            $helperIds = $mode === 'individual'
                ? array_values(array_unique(array_map('intval', $data['helper_ids'])))
                : array_values(array_unique(array_map('intval', $override['helper_ids'] ?? [])));
            $days = $mode === 'individual'
                ? array_values(array_unique(array_map('intval', $data['days_of_week'])))
                : array_values(array_unique(array_map('intval', $group->days_of_week ?? [])));

            if ($helperIds === [] || $days === []) {
                throw ValidationException::withMessages(['group_id' => 'El grupo «'.$group->name.'» debe tener ayudantes y días de trabajo configurados.']);
            }
            $shift = $mode === 'individual' && isset($data['shift_id']) ? Shift::findOrFail($data['shift_id']) : $group->shift;
            $vehicle = $mode === 'individual' && isset($data['vehicle_id']) ? Vehicle::findOrFail($data['vehicle_id']) : $group->vehicle;
            $this->assertEligibleCrew($driverId, $helperIds, $group, $vehicle);
            $definitions[] = [
                'group_id' => (int) $group->id,
                'group_name' => $group->name,
                'start_date' => Carbon::parse($data['start_date'])->toDateString(),
                'end_date' => Carbon::parse($data['end_date'])->toDateString(),
                'shift_id' => (int) $shift->id,
                'shift_name' => $shift->name,
                'shift_start_time' => $shift->start_time,
                'shift_end_time' => $shift->end_time,
                'zone_id' => (int) $group->zone_id,
                'zone_name' => $group->zone->name,
                'vehicle_id' => (int) $vehicle->id,
                'vehicle_label' => trim($vehicle->plate.' · '.$vehicle->name),
                'driver_id' => $driverId,
                'driver_name' => Employee::find($driverId)?->full_name ?? $group->driver->full_name,
                'helper_ids' => $helperIds,
                'helper_names' => Employee::whereIn('id', $helperIds)->orderBy('last_name')->get()->map->full_name->values()->all(),
                'days_of_week' => $days,
                'holiday_ids' => ! empty($data['holidays_configured']) ? array_map('intval', $data['holiday_ids'] ?? []) : null,
            ];
        }

        return $definitions;
    }

    private function assertEligibleCrew(int $driverId, array $helperIds, PersonnelGroup $group, ?Vehicle $vehicle = null): void
    {
        if (! $this->employeesOfType('Conductor')->whereKey($driverId)->exists()) {
            throw ValidationException::withMessages(['driver_id' => 'El conductor debe estar activo y ser de tipo Conductor.']);
        }
        if ($this->employeesOfType('Ayudante')->whereIn('id', $helperIds)->count() !== count($helperIds)) {
            throw ValidationException::withMessages(['helper_ids' => 'Todos los ayudantes deben estar activos y ser de tipo Ayudante.']);
        }
        if (in_array($driverId, $helperIds, true)) {
            throw ValidationException::withMessages(['helper_ids' => 'El conductor no puede figurar también como ayudante.']);
        }
        $vehicle ??= $group->vehicle;
        if (count($helperIds) + 1 > (int) $vehicle->occupant_capacity) {
            throw ValidationException::withMessages(['helper_ids' => 'La cantidad de personal supera la capacidad del vehículo '.$vehicle->plate.'.']);
        }
    }

    private function tokenPayload(array $definitions, array $blockedDates): string
    {
        return json_encode([
            'definitions' => array_map(fn ($item) => [
                $item['group_id'], $item['start_date'], $item['end_date'], $item['driver_id'],
                $item['helper_ids'], $item['days_of_week'], $item['shift_id'], $item['vehicle_id'], $item['holiday_ids'] ?? null,
            ], $definitions),
            'blocked_dates' => $blockedDates,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function availabilityToken(array $definitions, array $blockedDates, ?int $issued = null): string
    {
        $issued ??= now()->timestamp;
        return $issued.'.'.hash_hmac('sha256', $issued.'|'.$this->tokenPayload($definitions, $blockedDates), config('app.key'));
    }

    private function assertAvailabilityToken(Request $request, array $definitions, array $blockedDates): void
    {
        $token = (string) $request->input('availability_token', '');
        if (! preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $matches)
            || now()->timestamp - (int) $matches[1] > 900
            || now()->timestamp < (int) $matches[1]) {
            abort(409, 'Vuelva a validar la disponibilidad antes de guardar.');
        }
        if (! hash_equals($this->availabilityToken($definitions, $blockedDates, (int) $matches[1]), $token)) {
            abort(409, 'La disponibilidad cambió después de validar. Vuelva a validar antes de guardar.');
        }
    }

    private function availableSegments(array $definition, array $blockedDates): array
    {
        if ($blockedDates === []) return [$definition];

        $blocked = array_fill_keys($blockedDates, true);
        $weekdays = array_fill_keys(array_map('intval', $definition['days_of_week']), true);
        $segments = [];
        $segmentStart = null;
        $segmentEnd = null;

        foreach (CarbonPeriod::create($definition['start_date'], $definition['end_date']) as $date) {
            if (! isset($weekdays[$date->dayOfWeekIso])) continue;
            $dateValue = $date->toDateString();

            if (isset($blocked[$dateValue])) {
                if ($segmentStart !== null) {
                    $segment = $definition;
                    $segment['start_date'] = $segmentStart;
                    $segment['end_date'] = $segmentEnd;
                    $segments[] = $segment;
                    $segmentStart = null;
                    $segmentEnd = null;
                }
                continue;
            }

            $segmentStart ??= $dateValue;
            $segmentEnd = $dateValue;
        }

        if ($segmentStart !== null) {
            $segment = $definition;
            $segment['start_date'] = $segmentStart;
            $segment['end_date'] = $segmentEnd;
            $segments[] = $segment;
        }

        return $segments;
    }

    private function attributes(array $definition, ?string $batchUuid = null): array
    {
        return [
            'batch_uuid' => $batchUuid,
            'personnel_group_id' => $definition['group_id'],
            'group_name' => $definition['group_name'],
            'shift_id' => $definition['shift_id'],
            'shift_name' => $definition['shift_name'],
            'shift_start_time' => $definition['shift_start_time'],
            'shift_end_time' => $definition['shift_end_time'],
            'zone_id' => $definition['zone_id'],
            'zone_name' => $definition['zone_name'],
            'vehicle_id' => $definition['vehicle_id'],
            'vehicle_label' => $definition['vehicle_label'],
            'driver_id' => $definition['driver_id'],
            'driver_name' => $definition['driver_name'],
            'helper_ids' => $definition['helper_ids'],
            'helper_names' => $definition['helper_names'],
            'days_of_week' => $definition['days_of_week'],
            'start_date' => $definition['start_date'],
            'end_date' => $definition['end_date'],
            'status' => $definition['status'] ?? 'Programada',
            'created_by' => Auth::id(),
        ];
    }

    private function snapshot(ProgrammingSchedule $schedule): array
    {
        return [
            'grupo' => $schedule->group_name,
            'periodo' => $schedule->start_date->format('d/m/Y').' - '.$schedule->end_date->format('d/m/Y'),
            'turno' => $schedule->shift_name.' ('.substr($schedule->shift_start_time, 0, 5).'–'.substr($schedule->shift_end_time, 0, 5).')',
            'zona' => $schedule->zone_name,
            'vehiculo' => $schedule->vehicle_label,
            'conductor' => $schedule->driver_name,
            'ayudantes' => implode(', ', $schedule->helper_names ?? []),
            'dias' => collect($schedule->days_of_week)->map(fn ($day) => PersonnelGroup::WEEKDAYS[(int) $day] ?? '')->filter()->implode(', '),
            'estado' => $schedule->status,
        ];
    }

    private function recordHistory(ProgrammingSchedule $schedule, string $action, ?array $before, ?array $after, string $notes): void
    {
        $schedule->histories()->create([
            'schedule_label' => $schedule->group_name.' · '.$schedule->start_date->format('d/m/Y').' – '.$schedule->end_date->format('d/m/Y'),
            'changed_by' => Auth::id(),
            'action' => $action,
            'before_data' => $before,
            'after_data' => $after,
            'notes' => $notes,
        ]);
    }
}


