<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Services\Vehicles\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceScheduleController extends Controller
{
    public function index(Request $request, Maintenance $maintenance)
    {
        if ($request->ajax()) {
            return DataTables::of($maintenance->schedules()->with(['vehicle', 'employee'])->select('maintenance_schedules.*'))
                ->addColumn('day_label', fn ($schedule) => MaintenanceSchedule::WEEKDAYS[$schedule->weekday])
                ->addColumn('vehicle_label', fn ($schedule) => $schedule->vehicle->plate.' · '.$schedule->vehicle->name)
                ->addColumn('employee_label', fn ($schedule) => $schedule->employee->full_name)
                ->addColumn('type_label', fn ($schedule) => MaintenanceSchedule::TYPES[$schedule->type])
                ->editColumn('start_time', fn ($schedule) => substr($schedule->start_time, 0, 5))
                ->editColumn('end_time', fn ($schedule) => substr($schedule->end_time, 0, 5))
                ->addColumn('actions', function ($schedule) use ($maintenance) {
                    $daysUrl = e(route('admin.maintenance-days.index', $schedule));
                    $editUrl = e(route('admin.maintenances.schedules.edit', [$maintenance, $schedule]));
                    $deleteUrl = e(route('admin.maintenances.schedules.destroy', [$maintenance, $schedule]));
                    $csrf = csrf_field();
                    $method = method_field('DELETE');

                    return <<<HTML
                        <div class="d-flex justify-content-center gap-1">
                            <a class="btn btn-sm btn-outline-info" href="{$daysUrl}" title="Ver días generados" aria-label="Ver días generados"><i class="bi bi-calendar-check"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-url="{$editUrl}" title="Editar horario" aria-label="Editar horario"><i class="bi bi-pencil-square"></i></button>
                            <form action="{$deleteUrl}" method="POST" class="frmEliminar" data-confirmation="Se eliminarán este horario y TODOS sus días generados, observaciones e imágenes. Esta acción no se puede deshacer.">
                                {$csrf}{$method}
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar horario" aria-label="Eliminar horario"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    HTML;
                })
                ->rawColumns(['actions'])->make(true);
        }
        return view('admin.maintenances.schedules.index', compact('maintenance'));
    }

    public function create(Maintenance $maintenance)
    {
        return view('admin.maintenances.schedules.create', $this->formData($maintenance));
    }

    public function edit(Maintenance $maintenance, MaintenanceSchedule $schedule)
    {
        $this->belongsTo($maintenance, $schedule);
        return view('admin.maintenances.schedules.edit', $this->formData($maintenance, $schedule));
    }

    public function store(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->saveSchedule($maintenance, $this->validated($request));
        return response()->json(['message' => 'Horario registrado y días generados automáticamente.']);
    }

    public function update(Request $request, Maintenance $maintenance, MaintenanceSchedule $schedule, MaintenanceService $service)
    {
        $this->belongsTo($maintenance, $schedule);
        $service->saveSchedule($maintenance, $this->validated($request), $schedule);
        return response()->json(['message' => 'Horario actualizado y días sincronizados.']);
    }

    public function destroy(Maintenance $maintenance, MaintenanceSchedule $schedule, MaintenanceService $service)
    {
        $this->belongsTo($maintenance, $schedule);
        $paths = DB::transaction(function () use ($maintenance, $schedule, $service) {
            $service->lockCalendar();
            $schedule = $maintenance->schedules()->findOrFail($schedule->id);
            $paths = $schedule->days()->whereNotNull('image_path')->pluck('image_path')->all();
            $schedule->delete();
            return $paths;
        });
        Storage::disk('public')->delete($paths);
        return response()->json(['message' => 'Horario, días generados e imágenes eliminados correctamente.']);
    }

    private function belongsTo(Maintenance $maintenance, MaintenanceSchedule $schedule): void
    {
        abort_unless($schedule->maintenance_id === $maintenance->id, 404);
    }

    private function formData(Maintenance $maintenance, ?MaintenanceSchedule $schedule = null): array
    {
        return [
            'maintenance' => $maintenance, 'schedule' => $schedule,
            'vehicles' => Vehicle::orderBy('plate')->get(),
            'employees' => Employee::orderBy('last_name')->orderBy('first_name')->get(),
            'types' => MaintenanceSchedule::TYPES, 'weekdays' => MaintenanceSchedule::WEEKDAYS,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', Rule::in(array_keys(MaintenanceSchedule::TYPES))],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], ['end_time.after' => 'La hora de fin debe ser posterior a la de inicio, dentro del mismo día.'],
            ['vehicle_id' => 'vehículo', 'employee_id' => 'responsable', 'type' => 'tipo de mantenimiento', 'weekday' => 'día', 'start_time' => 'hora de inicio', 'end_time' => 'hora de fin']);
    }
}
