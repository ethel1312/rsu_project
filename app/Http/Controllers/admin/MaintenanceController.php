<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceController extends Controller
{
    private const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    private const TYPES = ['Preventivo', 'Limpieza', 'Reparación'];
    private const DAY_STATES = ['Pendiente', 'Realizado', 'No realizado'];

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Maintenance::query()->select('maintenances.*')->withCount('schedules');

            return DataTables::of($query)
                ->editColumn('start_date', fn (Maintenance $maintenance) => $maintenance->start_date->format('d/m/Y'))
                ->editColumn('end_date', fn (Maintenance $maintenance) => $maintenance->end_date->format('d/m/Y'))
                ->addColumn('schedule_count', fn (Maintenance $maintenance) => '<span class="maintenance-schedule-count">'.(int) $maintenance->schedules_count.' horarios</span>')
                ->addColumn('actions', fn (Maintenance $maintenance) => view('admin.maintenances.template.actions', compact('maintenance'))->render())
                ->rawColumns(['schedule_count', 'actions'])
                ->make(true);
        }

        return view('admin.maintenances.index');
    }

    public function create()
    {
        return view('admin.maintenances.template.form', ['maintenance' => new Maintenance(), 'hasGeneratedDays' => false]);
    }

    public function store(Request $request)
    {
        $data = $this->periodData($request);
        DB::transaction(function () use ($data) {
            Maintenance::query()->lockForUpdate()->get(['id']);
            $this->assertPeriodAvailable($data['start_date'], $data['end_date']);
            Maintenance::create($data);
        }, 3);

        return response()->json(['message' => 'Mantenimiento registrado correctamente.']);
    }

    public function edit(Maintenance $maintenance)
    {
        $hasGeneratedDays = $maintenance->schedules()->whereHas('days')->exists();

        return view('admin.maintenances.template.form', compact('maintenance', 'hasGeneratedDays'));
    }

    public function update(Request $request, Maintenance $maintenance)
    {
        $data = $this->periodData($request, $maintenance);
        DB::transaction(function () use ($data, $maintenance) {
            $locked = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);
            $hasGeneratedDays = $locked->schedules()->whereHas('days')->exists();
            $datesChanged = $data['start_date'] !== $locked->start_date->toDateString()
                || $data['end_date'] !== $locked->end_date->toDateString();
            if ($hasGeneratedDays && $datesChanged) {
                throw ValidationException::withMessages([
                    'start_date' => 'No se pueden cambiar las fechas porque este mantenimiento ya tiene horarios y días generados. Puede editar el nombre.',
                ]);
            }

            $this->assertPeriodAvailable($data['start_date'], $data['end_date'], $locked->id);
            foreach ($locked->schedules()->with('days')->get() as $schedule) {
                $this->assertScheduleDoesNotConflict(
                    $locked,
                    $schedule->only(['vehicle_id', 'employee_id', 'day_of_week', 'start_time', 'end_time']),
                    $schedule->id,
                    $data['start_date'],
                    $data['end_date'],
                );
                $this->syncDays($schedule, $data['start_date'], $data['end_date']);
            }
            $locked->update($data);
        }, 3);

        return response()->json(['message' => 'Mantenimiento actualizado correctamente.']);
    }

    public function destroy(Maintenance $maintenance)
    {
        $days = MaintenanceDay::whereHas('schedule', fn (Builder $query) => $query->where('maintenance_id', $maintenance->id));
        $hasRecordedDays = (clone $days)
            ->where('status', '!=', 'Pendiente')->exists();
        if ($hasRecordedDays) {
            return response()->json(['error' => 'No se puede eliminar: este mantenimiento ya tiene días registrados como realizados o no realizados.'], 409);
        }
        $imagePaths = $days->whereNotNull('image_path')->pluck('image_path');
        $maintenance->delete();
        foreach ($imagePaths as $imagePath) Storage::disk('public')->delete($imagePath);

        return response()->json(['message' => 'Mantenimiento y horarios eliminados correctamente.']);
    }

    public function schedules(Request $request, Maintenance $maintenance)
    {
        if ($request->ajax()) {
            $query = $maintenance->schedules()->with(['vehicle', 'employee'])->select('maintenance_schedules.*');

            return DataTables::of($query)
                ->addColumn('vehicle_label', fn (MaintenanceSchedule $schedule) => e($schedule->vehicle->code.' · '.$schedule->vehicle->name))
                ->addColumn('employee_label', fn (MaintenanceSchedule $schedule) => e($schedule->employee->full_name))
                ->addColumn('weekday_label', fn (MaintenanceSchedule $schedule) => self::WEEKDAYS[$schedule->day_of_week])
                ->addColumn('time_range', fn (MaintenanceSchedule $schedule) => substr($schedule->start_time, 0, 5).' – '.substr($schedule->end_time, 0, 5))
                ->addColumn('days_link', fn (MaintenanceSchedule $schedule) => '<a class="btn btn-sm btn-info text-white" href="'.route('admin.maintenances.days', $schedule).'" title="Ver días generados"><i class="bi bi-calendar-week me-1"></i>Ver días</a>')
                ->addColumn('edit_action', fn (MaintenanceSchedule $schedule) => '<button type="button" class="btn btn-sm btn-primary btnEditarHorario" data-url="'.route('admin.maintenances.schedules.edit', $schedule).'" title="Editar"><i class="bi bi-pencil-square"></i></button>')
                ->addColumn('delete_action', fn (MaintenanceSchedule $schedule) => '<form action="'.route('admin.maintenances.schedules.destroy', $schedule).'" method="POST" class="m-0 frmEliminarHorario">'.csrf_field().method_field('DELETE').'<button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash3-fill"></i></button></form>')
                ->rawColumns(['days_link', 'edit_action', 'delete_action'])
                ->make(true);
        }

        return view('admin.maintenances.schedules', compact('maintenance'));
    }

    public function createSchedule(Maintenance $maintenance)
    {
        return view('admin.maintenances.template.schedule-form', $this->scheduleFormData($maintenance, new MaintenanceSchedule()));
    }

    public function storeSchedule(Request $request, Maintenance $maintenance)
    {
        $data = $this->scheduleData($request);
        DB::transaction(function () use ($maintenance, $data) {
            $lockedPeriod = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);
            $this->assertScheduleDoesNotConflict($lockedPeriod, $data);
            $schedule = $lockedPeriod->schedules()->create($data);
            $this->syncDays($schedule, $lockedPeriod->start_date->toDateString(), $lockedPeriod->end_date->toDateString());
        }, 3);

        return response()->json(['message' => 'Horario guardado y días del mantenimiento generados.']);
    }

    public function editSchedule(MaintenanceSchedule $schedule)
    {
        return view('admin.maintenances.template.schedule-form', $this->scheduleFormData($schedule->maintenance, $schedule));
    }

    public function updateSchedule(Request $request, MaintenanceSchedule $schedule)
    {
        $data = $this->scheduleData($request);
        DB::transaction(function () use ($schedule, $data) {
            $locked = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $period = Maintenance::query()->lockForUpdate()->findOrFail($locked->maintenance_id);
            if ($locked->days()->exists() && (int) $data['day_of_week'] !== (int) $locked->day_of_week) {
                throw ValidationException::withMessages([
                    'day_of_week' => 'No se puede cambiar el día de la semana porque este horario ya tiene días generados.',
                ]);
            }

            $this->assertScheduleDoesNotConflict($period, $data, $locked->id);
            $this->syncDays($locked, $period->start_date->toDateString(), $period->end_date->toDateString(), (int) $data['day_of_week']);
            $locked->update($data);
        }, 3);

        return response()->json(['message' => 'Horario actualizado y días generados correctamente.']);
    }

    public function destroySchedule(MaintenanceSchedule $schedule)
    {
        if ($schedule->days()->where('status', '!=', 'Pendiente')->exists()) {
            return response()->json(['error' => 'No se puede eliminar: el horario tiene días con mantenimiento registrado.'], 409);
        }
        $imagePaths = $schedule->days()->whereNotNull('image_path')->pluck('image_path');
        $schedule->delete();
        foreach ($imagePaths as $imagePath) Storage::disk('public')->delete($imagePath);

        return response()->json(['message' => 'Horario y días generados eliminados correctamente.']);
    }

    public function days(Request $request, MaintenanceSchedule $schedule)
    {
        if ($request->ajax()) {
            $query = $schedule->days()->select('maintenance_days.*');

            return DataTables::of($query)
                ->addColumn('date_label', fn (MaintenanceDay $day) => $day->maintenance_date->format('d/m/Y'))
                ->editColumn('status', fn (MaintenanceDay $day) => match ($day->status) {
                    'Realizado' => '<span class="maintenance-day-status maintenance-day-status-done">Realizado</span>',
                    'No realizado' => '<span class="maintenance-day-status maintenance-day-status-missed">No realizado</span>',
                    default => '<span class="maintenance-day-status maintenance-day-status-pending">Pendiente</span>',
                })
                ->addColumn('image_label', function (MaintenanceDay $day) {
                    if (! $day->image_path) {
                        return '<span class="text-muted">—</span>';
                    }

                    // Use the current request host and port, as the employee image list does.
                    $imageUrl = e(asset('storage/'.$day->image_path));
                    $date = e($day->maintenance_date->format('d/m/Y'));

                    return '<img src="'.$imageUrl.'" class="maintenance-day-thumbnail btnVerImagenMantenimiento" data-image="'.$imageUrl.'" data-date="'.$date.'" alt="Mantenimiento del '.$date.'" title="Ver imagen ampliada">';
                })
                ->addColumn('actions', fn (MaintenanceDay $day) => '<button type="button" class="btn btn-sm btn-primary btnEditarDia" data-url="'.route('admin.maintenances.days.edit', $day).'"><i class="bi bi-pencil-square"></i></button>')
                ->rawColumns(['status', 'image_label', 'actions'])
                ->make(true);
        }

        $schedule->load(['maintenance', 'vehicle', 'employee']);
        return view('admin.maintenances.days', compact('schedule'));
    }

    public function editDay(MaintenanceDay $day)
    {
        return view('admin.maintenances.template.day-form', compact('day'));
    }

    public function updateDay(Request $request, MaintenanceDay $day)
    {
        $data = $request->validate([
            'observation' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(self::DAY_STATES)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ], [
            'image.image' => 'Seleccione un archivo de imagen válido.',
            'image.max' => 'La imagen no puede pesar más de 5 MB.',
        ]);

        $oldImage = $day->image_path;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('maintenance-days', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }
        unset($data['image'], $data['remove_image']);
        $day->update($data);
        if ($oldImage && $oldImage !== $day->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return response()->json(['message' => 'Día de mantenimiento actualizado correctamente.']);
    }

    private function periodData(Request $request, ?Maintenance $maintenance = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'name.required' => 'Ingrese el nombre del mantenimiento.',
            'start_date.required' => 'Seleccione la fecha de inicio.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ]);

        if (Carbon::parse($data['end_date'])->lt(Carbon::parse($data['start_date']))) {
            throw ValidationException::withMessages(['end_date' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.']);
        }

        return $data;
    }

    private function assertPeriodAvailable(string $start, string $end, ?int $ignoreId = null): void
    {
        $overlap = Maintenance::query()->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'El rango de fechas se cruza con otro mantenimiento registrado. Elija fechas que no se solapen.']);
        }
    }

    private function scheduleData(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('status', true)->whereIn(
                'employee_type_id',
                DB::table('employee_types')->select('id')->where('name', 'Mantenimiento'),
            )],
            'type' => ['required', Rule::in(self::TYPES)],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'vehicle_id.required' => 'Seleccione un vehículo.',
            'employee_id.required' => 'Seleccione un responsable activo.',
            'employee_id.exists' => 'Seleccione un responsable activo de tipo Mantenimiento.',
            'type.in' => 'Seleccione un tipo de mantenimiento válido.',
            'day_of_week.between' => 'Seleccione un día de la semana válido.',
            'start_time.date_format' => 'La hora de inicio debe tener formato HH:MM.',
            'end_time.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ]);
    }

    private function assertScheduleDoesNotConflict(Maintenance $maintenance, array $data, ?int $ignoreId = null, ?string $rangeStart = null, ?string $rangeEnd = null): void
    {
        $dates = $this->datesForWeekday(
            $rangeStart ?? $maintenance->start_date->toDateString(),
            $rangeEnd ?? $maintenance->end_date->toDateString(),
            (int) $data['day_of_week'],
        );
        if ($dates === []) {
            throw ValidationException::withMessages(['day_of_week' => 'Ese día de la semana no ocurre dentro del rango de fechas del mantenimiento.']);
        }

        // Use a stable lock order so two concurrent saves cannot reserve the same vehicle/person at once.
        $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
        $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
        $vehicleLabel = trim($vehicle->code.' · '.$vehicle->name.' · '.$vehicle->plate);
        $conflicts = ['vehicle' => [], 'employee' => []];
        $recordConflict = function (string $kind, string $resource, string $activity, string $occupiedStart, string $occupiedEnd, string $date) use (&$conflicts, $data): void {
            foreach ($this->overlappingTimeRanges($data['start_time'], $data['end_time'], $occupiedStart, $occupiedEnd) as [$overlapStart, $overlapEnd]) {
                $conflicts[$kind][] = [
                    'resource' => $resource,
                    'activity' => $activity,
                    'occupied_start' => substr($occupiedStart, 0, 5),
                    'occupied_end' => substr($occupiedEnd, 0, 5),
                    'overlap_start' => $overlapStart,
                    'overlap_end' => $overlapEnd,
                    'date' => $date,
                ];
            }
        };
        foreach ($dates as $date) {
            $query = MaintenanceSchedule::query()
                ->join('maintenances as maintenance_periods', 'maintenance_periods.id', '=', 'maintenance_schedules.maintenance_id')
                ->join('vehicles as scheduled_vehicles', 'scheduled_vehicles.id', '=', 'maintenance_schedules.vehicle_id')
                ->whereDate('maintenance_periods.start_date', '<=', $date)
                ->whereDate('maintenance_periods.end_date', '>=', $date)
                ->where('maintenance_schedules.day_of_week', Carbon::parse($date)->dayOfWeekIso)
                ->where('maintenance_schedules.start_time', '<', $data['end_time'])
                ->where('maintenance_schedules.end_time', '>', $data['start_time'])
                ->when($ignoreId, fn (Builder $builder) => $builder->where('maintenance_schedules.id', '!=', $ignoreId));

            $vehicleBookings = (clone $query)->where('maintenance_schedules.vehicle_id', $data['vehicle_id'])
                ->get(['maintenance_schedules.*', 'maintenance_periods.name as period_name']);
            foreach ($vehicleBookings as $booking) {
                $recordConflict('vehicle', $vehicleLabel, 'el mantenimiento «'.$booking->period_name.'»', $booking->start_time, $booking->end_time, $date);
            }
            $employeeBookings = (clone $query)->where('maintenance_schedules.employee_id', $data['employee_id'])
                ->get(['maintenance_schedules.*', 'maintenance_periods.name as period_name', 'scheduled_vehicles.code as booked_vehicle_code', 'scheduled_vehicles.name as booked_vehicle_name']);
            foreach ($employeeBookings as $booking) {
                $bookedVehicle = trim($booking->booked_vehicle_code.' · '.$booking->booked_vehicle_name);
                $recordConflict('employee', $employee->full_name, 'el mantenimiento «'.$booking->period_name.'» del vehículo «'.$bookedVehicle.'»', $booking->start_time, $booking->end_time, $date);
            }

        }

        $errors = [];
        if ($conflicts['vehicle'] !== []) $errors['vehicle_id'] = $this->formatConflictMessages('vehicle', $vehicleLabel, $conflicts['vehicle'], $data);
        if ($conflicts['employee'] !== []) $errors['employee_id'] = $this->formatConflictMessages('employee', $employee->full_name, $conflicts['employee'], $data);
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    private function syncDays(MaintenanceSchedule $schedule, string $start, string $end, ?int $weekday = null): void
    {
        $weekday ??= (int) $schedule->day_of_week;
        $dates = $this->datesForWeekday($start, $end, $weekday);
        if ($dates === []) {
            throw ValidationException::withMessages(['day_of_week' => 'Ese día de la semana no ocurre dentro del rango de fechas del mantenimiento.']);
        }
        $staleDays = $schedule->days()->whereNotIn('maintenance_date', $dates)->get();
        if ($staleDays->contains(fn (MaintenanceDay $day) => $day->status !== 'Pendiente')) {
            throw ValidationException::withMessages(['schedule' => 'No se puede cambiar el rango o día semanal porque dejaría fuera días con resultados registrados.']);
        }
        $staleImages = $staleDays->pluck('image_path')->filter();
        $schedule->days()->whereIn('id', $staleDays->pluck('id'))->delete();
        if ($staleImages->isNotEmpty()) {
            DB::afterCommit(fn () => $staleImages->each(fn ($path) => Storage::disk('public')->delete($path)));
        }
        foreach ($dates as $date) {
            $schedule->days()->firstOrCreate(['maintenance_date' => $date], ['status' => 'Pendiente']);
        }
    }

    private function datesForWeekday(string $start, string $end, int $weekday): array
    {
        $dates = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->dayOfWeekIso === $weekday) $dates[] = $date->toDateString();
        }
        return $dates;
    }

    private function overlappingTimeRanges(string $firstStart, string $firstEnd, string $secondStart, string $secondEnd): array
    {
        $toMinutes = fn (string $time) => ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
        $segments = function (string $start, string $end) use ($toMinutes): array {
            $from = $toMinutes($start);
            $to = $toMinutes($end);
            return $to > $from ? [[$from, $to]] : [[$from, 1440], [0, $to]];
        };
        $overlaps = [];
        foreach ($segments($firstStart, $firstEnd) as [$firstFrom, $firstTo]) {
            foreach ($segments($secondStart, $secondEnd) as [$secondFrom, $secondTo]) {
                $from = max($firstFrom, $secondFrom);
                $to = min($firstTo, $secondTo);
                if ($from < $to) $overlaps[] = [$this->minutesToTime($from), $this->minutesToTime($to)];
            }
        }
        return $overlaps;
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    private function formatConflictMessages(string $kind, string $resource, array $conflicts, array $data): string
    {
        $groups = [];
        foreach ($conflicts as $conflict) {
            $key = implode('|', [$conflict['activity'], $conflict['occupied_start'], $conflict['occupied_end'], $conflict['overlap_start'], $conflict['overlap_end']]);
            $groups[$key] ??= $conflict + ['dates' => []];
            $groups[$key]['dates'][] = $conflict['date'];
        }

        $weekday = self::WEEKDAYS[(int) $data['day_of_week']];
        $proposed = substr($data['start_time'], 0, 5).'–'.substr($data['end_time'], 0, 5);
        $messages = [];
        foreach ($groups as $conflict) {
            $dates = implode(', ', array_map(fn ($date) => Carbon::parse($date)->format('d/m/Y'), array_unique($conflict['dates'])));
            $resourceName = $kind === 'vehicle' ? 'El vehículo' : 'El responsable';
            $messages[] = $resourceName.' «'.$resource.'» ya tiene ocupado el horario de '.$conflict['occupied_start'].' a '.$conflict['occupied_end'].' por '.$conflict['activity'].'. El horario propuesto ('.$proposed.') se cruza de '.$conflict['overlap_start'].' a '.$conflict['overlap_end'].' los '.$weekday.' en estas fechas: '.$dates.'.';
        }

        return implode("\n", $messages);
    }

    private function scheduleFormData(Maintenance $maintenance, MaintenanceSchedule $schedule): array
    {
        return [
            'maintenance' => $maintenance,
            'schedule' => $schedule,
            'weekdayLocked' => $schedule->exists && $schedule->days()->exists(),
            'vehicles' => Vehicle::orderBy('code')->get(),
            'employees' => Employee::where('status', true)
                ->whereHas('employeeType', fn (Builder $query) => $query->where('name', 'Mantenimiento'))
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'weekdays' => self::WEEKDAYS,
            'types' => self::TYPES,
        ];
    }
}
