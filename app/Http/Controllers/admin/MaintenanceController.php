<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Services\Vehicles\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(Maintenance::query())
                ->addColumn('actions', function ($maintenance) {
                    $schedulesUrl = e(route('admin.maintenances.schedules.index', $maintenance));
                    $editUrl = e(route('admin.maintenances.edit', $maintenance));
                    $deleteUrl = e(route('admin.maintenances.destroy', $maintenance));
                    $csrf = csrf_field();
                    $method = method_field('DELETE');

                    return <<<HTML
                        <div class="d-flex justify-content-center gap-1">
                            <a class="btn btn-sm btn-outline-info" href="{$schedulesUrl}" title="Ver horarios" aria-label="Ver horarios"><i class="bi bi-calendar-week"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-url="{$editUrl}" title="Editar mantenimiento" aria-label="Editar mantenimiento"><i class="bi bi-pencil-square"></i></button>
                            <form action="{$deleteUrl}" method="POST" class="frmEliminar" data-confirmation="Se eliminará este mantenimiento. Solo se permite si no tiene horarios registrados.">
                                {$csrf}{$method}
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar mantenimiento" aria-label="Eliminar mantenimiento"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    HTML;
                })
                ->rawColumns(['actions'])->make(true);
        }

        return view('admin.maintenances.index');
    }

    public function create() { return view('admin.maintenances.create'); }
    public function edit(Maintenance $maintenance) { return view('admin.maintenances.edit', compact('maintenance')); }

    public function store(Request $request, MaintenanceService $service)
    {
        $service->savePeriod($this->validated($request));
        return response()->json(['message' => 'Mantenimiento registrado exitosamente.']);
    }

    public function update(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->savePeriod($this->validated($request), $maintenance);
        return response()->json(['message' => 'Mantenimiento actualizado. Los días de sus horarios fueron sincronizados.']);
    }

    public function destroy(Maintenance $maintenance, MaintenanceService $service)
    {
        return DB::transaction(function () use ($maintenance, $service) {
            $service->lockCalendar();
            $maintenance = Maintenance::findOrFail($maintenance->id);
            if ($maintenance->schedules()->exists()) {
                return response()->json(['message' => 'No se puede eliminar un mantenimiento con horarios. Elimine primero sus horarios y confirme la eliminación de sus días.'], 409);
            }
            $maintenance->delete();
            return response()->json(['message' => 'Mantenimiento eliminado correctamente.']);
        });
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], ['end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.'],
            ['name' => 'nombre', 'start_date' => 'fecha de inicio', 'end_date' => 'fecha de fin']);
    }
}
