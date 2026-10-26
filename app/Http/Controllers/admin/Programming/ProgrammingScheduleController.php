<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Vehicle;
use App\Models\Programming\Holiday;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ProgrammingScheduleHistory;
use App\Models\Programming\Shift;
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
                    if (! in_array($date->dayOfWeekIso, $weekdays, true)
                        || in_array($date->toDateString(), $schedule->excluded_dates ?? [], true)) continue;

                    $serviceDate = $date->toDateString();
                    $actions = view('admin.programming.schedules.template.actions', compact('schedule', 'serviceDate'))->render();
                    $rows->push([
                        'date' => $date->format('d/m/Y'),
                        'date_sort' => $date->toDateString(),
                        'status_badge' => $schedule->status === 'Finalizada'
                            ? '<span class="badge schedule-status schedule-status-finished">Finalizada</span>'
                            : ($schedule->status === 'Reprogramado'
                                ? '<span class="badge schedule-status bg-warning">Reprogramado</span>'
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

    public function holidays(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        return response()->json(Holiday::active()->whereBetween('date', [$data['start_date'], $data['end_date']])
            ->orderBy('date')->get(['id', 'date', 'description']));
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

    public function validateAvailability(Request $request)
    {
        $request->validate(['schedule_id' => ['nullable', 'integer', 'exists:programming_schedules,id']]);
        $scheduleId = $request->filled('schedule_id') ? (int) $request->input('schedule_id') : null;
        if ($scheduleId && ! in_array(ProgrammingSchedule::findOrFail($scheduleId)->status, ['Programada', 'Reprogramado'], true)) {
            return response()->json(['message' => 'Solo se pueden editar programaciones activas.'], 409);
        }
        $schedule = $scheduleId ? ProgrammingSchedule::findOrFail($scheduleId) : null;
        $definitions = $schedule ? [$this->dailyDefinition($request, $schedule)] : $this->definitions($request);
        $result = $this->availability->check($definitions, $scheduleId, $schedule ? $definitions[0]['start_date'] : null);
        $available = $result['service_days'] > 0;

        return response()->json([
            'available' => $available,
            'token' => $available ? $this->availabilityToken($definitions) : null,
            'conflicts' => $result['conflicts'],
            'service_days' => $result['service_days'],
            'skipped_days' => $result['skipped_days'],
            'omissions' => $result['omissions'],
            'program_count' => count($result['definitions']),
            'warnings' => $result['warnings'],
            'suggestions' => $result['suggestions'],
            'message' => $available
                ? 'Puede guardar la programación de los días disponibles.'
                : 'No hay días disponibles para programar.',
        ], $available ? 200 : 409);
    }

    public function store(Request $request)
    {
        $definitions = $this->definitions($request);
        $this->assertAvailabilityToken($request, $definitions);
        $result = $this->availability->check($definitions);

        if ($result['conflicts'] !== []) {
            return response()->json(['available' => false, 'conflicts' => $result['conflicts'], 'warnings' => $result['warnings'], 'suggestions' => $result['suggestions'], 'omissions' => $result['omissions']], 409);
        }

        $definitions = $result['definitions'];
        $batchUuid = $request->input('mode') === 'mass' ? (string) Str::uuid() : null;
        $created = DB::transaction(function () use ($definitions, $batchUuid) {
            return collect($definitions)->map(function ($definition) use ($batchUuid) {
                $schedule = ProgrammingSchedule::create($this->attributes($definition, $batchUuid));
                $this->recordHistory($schedule, $batchUuid ? 'created_mass' : 'created', null, $this->snapshot($schedule), $batchUuid ? 'Programación creada en lote.' : 'Programación creada.');
                return $schedule;
            });
        });

        return response()->json([
            'message' => count($created) > 1
                ? count($created).' programaciones registradas exitosamente.'
                : 'Programación registrada exitosamente.',
            'service_days' => $result['service_days'],
            'skipped_days' => $result['skipped_days'],
            'omissions' => $result['omissions'],
            'warnings' => $result['warnings'],
            'suggestions' => $result['suggestions'],
        ]);
    }

    public function edit(Request $request, ProgrammingSchedule $schedule)
    {
        abort_unless(in_array($schedule->status, ['Programada', 'Reprogramado'], true), 409, 'Solo se pueden modificar programaciones activas.');
        $date = $this->scheduledDate($request, $schedule);
        return view('admin.programming.schedules.edit-day', array_merge($this->formData($schedule), [
            'serviceDate' => $date,
            'shifts' => Shift::orderBy('name')->get(),
            'vehicles' => Vehicle::orderBy('plate')->get(),
        ]));
    }

    public function update(Request $request, ProgrammingSchedule $schedule)
    {
        $definition = $this->dailyDefinition($request, $schedule);
        $this->assertAvailabilityToken($request, [$definition]);

        return DB::transaction(function () use ($request, $schedule) {
            $schedule = ProgrammingSchedule::lockForUpdate()->findOrFail($schedule->id);
            $definition = $this->dailyDefinition($request, $schedule);
            $result = $this->availability->check([$definition], $schedule->id, $definition['start_date']);
            if ($result['conflicts'] !== []) {
                return response()->json([
                    'conflicts' => $result['conflicts'], 'omissions' => $result['omissions'],
                    'warnings' => $result['warnings'], 'suggestions' => $result['suggestions'],
                ], 409);
            }
            $before = $this->snapshot($schedule);
            $attributes = $this->attributes($result['definitions'][0], $schedule->batch_uuid);
            $attributes['status'] = 'Reprogramado';
            if ($schedule->parent_schedule_id) {
                unset($attributes['created_by']);
                $schedule->update($attributes);
                $changed = $schedule;
            } else {
                $schedule->update(['excluded_dates' => array_values(array_unique(array_merge(
                    $schedule->excluded_dates ?? [], [$definition['start_date']]
                )))]);
                $attributes['parent_schedule_id'] = $schedule->id;
                $changed = ProgrammingSchedule::create($attributes);
            }
            $before['periodo'] = Carbon::parse($definition['start_date'])->format('d/m/Y');
            $before['dias'] = PersonnelGroup::WEEKDAYS[Carbon::parse($definition['start_date'])->dayOfWeekIso];
            $before['fechas_omitidas'] = '';
            $after = $this->snapshot($changed);
            $after['periodo'] = $before['periodo'];
            $notes = collect($definition['change_reasons'])->map(fn ($reason, $field) => $field.': '.$reason)->implode(' | ');
            $this->recordHistory($changed, 'rescheduled', $before, $after, $notes);
            return response()->json([
                'message' => 'El día seleccionado fue reprogramado. Los demás días conservan su programación.',
                'warnings' => $result['warnings'], 'omissions' => [], 'suggestions' => $result['suggestions'],
            ]);
        });
    }

    private function scheduledDate(Request $request, ProgrammingSchedule $schedule): string
    {
        $data = $request->validate(['date' => ['required', 'date', 'after_or_equal:today']]);
        $date = Carbon::parse($data['date']);
        abort_unless(
            $date->toDateString() >= $schedule->start_date->toDateString()
            && $date->toDateString() <= $schedule->end_date->toDateString()
            && in_array($date->dayOfWeekIso, array_map('intval', $schedule->days_of_week ?? []), true)
            && ! in_array($date->toDateString(), $schedule->excluded_dates ?? [], true),
            409, 'El día seleccionado ya no pertenece a esta programación. Actualice la lista.'
        );
        return $date->toDateString();
    }

    private function dailyDefinition(Request $request, ProgrammingSchedule $schedule): array
    {
        abort_unless(in_array($schedule->status, ['Programada', 'Reprogramado'], true), 409, 'Solo se pueden modificar programaciones activas.');
        $date = $this->scheduledDate($request, $schedule);
        $data = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['required', 'integer', 'exists:employees,id'],
            'helper_ids' => ['required', 'array', 'min:1'],
            'helper_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'reasons' => ['required', 'array'],
            'reasons.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $helpers = array_map('intval', $data['helper_ids']);
        $oldHelpers = array_map('intval', $schedule->helper_ids ?? []);
        sort($helpers);
        sort($oldHelpers);
        $changes = [
            'turno' => (int) $data['shift_id'] !== (int) $schedule->shift_id,
            'vehiculo' => (int) $data['vehicle_id'] !== (int) $schedule->vehicle_id,
            'conductor' => (int) $data['driver_id'] !== (int) $schedule->driver_id,
            'ayudantes' => $helpers !== $oldHelpers,
        ];
        $reasons = [];
        foreach ($changes as $field => $changed) {
            if (! $changed) continue;
            $reason = trim($data['reasons'][$field] ?? '');
            if ($reason === '') throw ValidationException::withMessages(['reasons.'.$field => 'Indique el motivo del cambio de '.$field.'.']);
            $reasons[$field] = $reason;
        }
        if ($reasons === []) throw ValidationException::withMessages(['changes' => 'Realice al menos un cambio para reprogramar este día.']);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $shift = Shift::findOrFail($data['shift_id']);
        $this->assertEligibleCrew((int) $data['driver_id'], $helpers, $vehicle);
        return [
            'group_id' => $schedule->personnel_group_id, 'group_name' => $schedule->group_name,
            'start_date' => $date, 'end_date' => $date, 'days_of_week' => [Carbon::parse($date)->dayOfWeekIso],
            'shift_id' => $shift->id, 'shift_name' => $shift->name,
            'shift_start_time' => $shift->start_time, 'shift_end_time' => $shift->end_time,
            'zone_id' => $schedule->zone_id, 'zone_name' => $schedule->zone_name,
            'vehicle_id' => $vehicle->id, 'vehicle_label' => trim($vehicle->plate.' · '.$vehicle->name),
            'driver_id' => (int) $data['driver_id'], 'driver_name' => Employee::findOrFail($data['driver_id'])->full_name,
            'helper_ids' => $helpers, 'helper_names' => Employee::whereIn('id', $helpers)->orderBy('last_name')->get()->map->full_name->values()->all(),
            'change_reasons' => $reasons,
        ];
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
        $rootId = $schedule->parent_schedule_id ?: $schedule->id;
        $familyIds = ProgrammingSchedule::where('id', $rootId)->orWhere('parent_schedule_id', $rootId)->pluck('id');
        $histories = ProgrammingScheduleHistory::whereIn('programming_schedule_id', $familyIds)->with('actor')->latest()->get();
        return view('admin.programming.schedules.history', compact('schedule', 'histories'));
    }
    public function allHistory()
    {
        $schedule = null;
        $histories = ProgrammingScheduleHistory::with('actor')->latest()->limit(250)->get();

        return view('admin.programming.schedules.history', compact('schedule', 'histories'));
    }

    private function formData(?ProgrammingSchedule $schedule = null): array
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
            'shifts' => Shift::orderBy('name')->get(),
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
            $rules['group_ids'] = ['required', 'array', 'min:1'];
            $rules['group_ids.*'] = ['required', 'integer', 'distinct', 'exists:personnel_groups,id'];
        } else {
            $rules['group_id'] = ['required', 'integer', 'exists:personnel_groups,id'];
            $rules['driver_id'] = ['required', 'integer', 'exists:employees,id'];
            $rules['helper_ids'] = ['required', 'array', 'min:1'];
            $rules['helper_ids.*'] = ['required', 'integer', 'distinct', 'exists:employees,id'];
            $rules['days_of_week'] = ['required', 'array', 'min:1'];
            $rules['days_of_week.*'] = ['required', 'integer', 'distinct', Rule::in(array_keys(PersonnelGroup::WEEKDAYS))];
        }

        if ($mode === 'mass') {
            $rules['groups'] = ['sometimes', 'array'];
            $rules['groups.*.driver_id'] = ['required', 'integer', 'exists:employees,id'];
            $rules['groups.*.helper_ids'] = ['required', 'array', 'min:1'];
            $rules['groups.*.helper_ids.*'] = ['required', 'integer', 'exists:employees,id'];
            $rules['holidays_configured'] = ['sometimes', 'boolean'];
            $rules['holiday_ids'] = ['sometimes', 'array'];
            $rules['holiday_ids.*'] = ['integer', 'distinct', 'exists:holidays,id'];
        }
        $data = $request->validate($rules);
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
            if (isset($data['groups']) && $override === null) {
                throw ValidationException::withMessages(['groups' => 'Revise el personal de todos los grupos seleccionados.']);
            }
            $driverId = $mode === 'individual' ? (int) $data['driver_id'] : (int) ($override['driver_id'] ?? $group->driver_id);
            $helperIds = $mode === 'individual'
                ? array_values(array_unique(array_map('intval', $data['helper_ids'])))
                : ($override ? array_values(array_unique(array_map('intval', $override['helper_ids']))) : $group->helpers->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
            $days = $mode === 'individual'
                ? array_values(array_unique(array_map('intval', $data['days_of_week'])))
                : array_values(array_unique(array_map('intval', $group->days_of_week ?? [])));

            if ($helperIds === [] || $days === []) {
                throw ValidationException::withMessages(['group_id' => 'El grupo «'.$group->name.'» debe tener ayudantes y días de trabajo configurados.']);
            }
            $this->assertEligibleCrew($driverId, $helperIds, $group->vehicle);
            $definitions[] = [
                'group_id' => (int) $group->id,
                'group_name' => $group->name,
                'start_date' => Carbon::parse($data['start_date'])->toDateString(),
                'end_date' => Carbon::parse($data['end_date'])->toDateString(),
                'shift_id' => (int) $group->shift_id,
                'shift_name' => $group->shift->name,
                'shift_start_time' => $group->shift->start_time,
                'shift_end_time' => $group->shift->end_time,
                'zone_id' => (int) $group->zone_id,
                'zone_name' => $group->zone->name,
                'vehicle_id' => (int) $group->vehicle_id,
                'vehicle_label' => trim($group->vehicle->plate.' · '.$group->vehicle->name),
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

    private function assertEligibleCrew(int $driverId, array $helperIds, Vehicle $vehicle): void
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
        if (count($helperIds) + 1 > (int) $vehicle->occupant_capacity) {
            throw ValidationException::withMessages(['helper_ids' => 'La cantidad de personal supera la capacidad del vehículo '.$vehicle->plate.'.']);
        }
    }

    private function tokenPayload(array $definitions): string
    {
        return json_encode(array_map(fn ($item) => [
            $item['group_id'], $item['start_date'], $item['end_date'], $item['driver_id'],
            $item['helper_ids'], $item['days_of_week'], $item['shift_id'], $item['vehicle_id'],
            $item['holiday_ids'] ?? null, $item['change_reasons'] ?? [],
        ], $definitions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function availabilityToken(array $definitions, ?int $issued = null): string
    {
        $issued ??= now()->timestamp;
        return $issued.'.'.hash_hmac('sha256', $issued.'|'.$this->tokenPayload($definitions), config('app.key'));
    }

    private function assertAvailabilityToken(Request $request, array $definitions): void
    {
        $token = (string) $request->input('availability_token', '');
        if (! preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $matches)
            || now()->timestamp - (int) $matches[1] > 900
            || now()->timestamp < (int) $matches[1]) {
            abort(409, 'Vuelva a validar la disponibilidad antes de guardar.');
        }
        if (! hash_equals($this->availabilityToken($definitions, (int) $matches[1]), $token)) {
            abort(409, 'Los datos cambiaron después de la validación. Valide nuevamente la disponibilidad.');
        }
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
            'excluded_dates' => $definition['excluded_dates'] ?? [],
            'start_date' => $definition['start_date'],
            'end_date' => $definition['end_date'],
            'status' => 'Programada',
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
            'fechas_omitidas' => implode(', ', $schedule->excluded_dates ?? []),
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


