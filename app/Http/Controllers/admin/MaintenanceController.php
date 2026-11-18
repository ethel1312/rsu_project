<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Services\MaintenanceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $service) {}

    public function index(Request $request)
    {
        $rows = Maintenance::withCount('schedules')->orderByDesc('start_date')->paginate(15);

        return $this->listing($request, 'periods', $rows);
    }

    public function schedules(Request $request, Maintenance $maintenance)
    {
        $rows = $maintenance->schedules()
            ->with(['vehicle', 'employee'])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate(15);

        return $this->listing($request, 'schedules', $rows, compact('maintenance'));
    }

    public function days(Request $request, Maintenance $maintenance, MaintenanceSchedule $schedule)
    {
        $schedule->load('vehicle');
        $rows = $schedule->days()->orderBy('date')->paginate(15);

        return $this->listing($request, 'days', $rows, compact('maintenance', 'schedule'));
    }

    public function create()
    {
        return view('admin.maintenances.forms.period', ['period' => null]);
    }

    public function store(Request $request)
    {
        $this->service->savePeriod(null, $this->periodData($request));

        return response()->json(['message' => 'Mantenimiento registrado.'], 201);
    }

    public function edit(Maintenance $maintenance)
    {
        return view('admin.maintenances.forms.period', ['period' => $maintenance]);
    }

    public function update(Request $request, Maintenance $maintenance)
    {
        $this->service->savePeriod($maintenance, $this->periodData($request));

        return response()->json(['message' => 'Mantenimiento actualizado.']);
    }

    public function destroy(Maintenance $maintenance)
    {
        $this->service->deletePeriod($maintenance);

        return response()->json(['message' => 'Mantenimiento eliminado.']);
    }

    public function createSchedule(Maintenance $maintenance)
    {
        return view('admin.maintenances.forms.schedule', $this->scheduleFormData($maintenance));
    }

    public function storeSchedule(Request $request, Maintenance $maintenance)
    {
        $this->service->saveSchedule($maintenance, null, $this->scheduleData($request));

        return response()->json(['message' => 'Horario registrado y días generados.'], 201);
    }

    public function updateSchedule(Request $request, Maintenance $maintenance, MaintenanceSchedule $schedule)
    {
        $this->service->saveSchedule($maintenance, $schedule, $this->scheduleData($request));

        return response()->json(['message' => 'Horario y días actualizados.']);
    }

    public function editSchedule(Maintenance $maintenance, MaintenanceSchedule $schedule)
    {
        return view('admin.maintenances.forms.schedule', $this->scheduleFormData($maintenance, $schedule));
    }

    public function destroySchedule(Maintenance $maintenance, MaintenanceSchedule $schedule)
    {
        $this->service->deleteSchedule($maintenance, $schedule);

        return response()->json(['message' => 'Horario y días generados eliminados.']);
    }

    public function editDay(Maintenance $maintenance, MaintenanceSchedule $schedule, MaintenanceDay $day)
    {
        return view('admin.maintenances.forms.day', compact('maintenance', 'schedule', 'day'));
    }

    public function updateDay(Request $request, Maintenance $maintenance, MaintenanceSchedule $schedule, MaintenanceDay $day)
    {
        $data = $request->validate([
            'observation' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(MaintenanceDay::STATUSES))],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ], [
            'image.image' => 'Seleccione una imagen válida.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen no puede superar 5 MB.',
        ]);

        $this->service->updateDay($schedule, $day, $data, $request->file('image'));

        return response()->json(['message' => 'Día de mantenimiento actualizado.']);
    }

    public function image(Maintenance $maintenance, MaintenanceSchedule $schedule, MaintenanceDay $day)
    {
        $disk = Storage::disk('local');
        abort_unless($day->image_path && $disk->exists($day->image_path), 404);

        return $disk->response($day->image_path, headers: ['Cache-Control' => 'private, no-cache']);
    }

    private function listing(Request $request, string $section, LengthAwarePaginator $rows, array $context = [])
    {
        $view = $request->ajax() ? 'table' : 'index';

        return view('admin.maintenances.'.$view, compact('section', 'rows') + $context);
    }

    private function scheduleFormData(Maintenance $maintenance, ?MaintenanceSchedule $schedule = null): array
    {
        return [
            'maintenance' => $maintenance,
            'schedule' => $schedule,
            'vehicles' => Vehicle::orderBy('plate')->get(['id', 'plate', 'name']),
            'employees' => Employee::where(fn ($query) => $query->where('status', true)
                ->orWhere('id', $schedule?->employee_id))
                ->orderBy('last_name')->orderBy('first_name')->get(),
        ];
    }

    private function periodData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:9999-12-31'],
        ], [
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ], ['name' => 'nombre', 'start_date' => 'fecha de inicio', 'end_date' => 'fecha de fin']);
    }

    private function scheduleData(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', Rule::in(array_keys(MaintenanceSchedule::TYPES))],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => 'La hora de fin debe ser posterior a la de inicio y pertenecer al mismo día.',
        ], [
            'vehicle_id' => 'vehículo', 'employee_id' => 'responsable', 'type' => 'tipo de mantenimiento',
            'weekday' => 'día de la semana', 'start_time' => 'hora de inicio', 'end_time' => 'hora de fin',
        ]);
    }
}
