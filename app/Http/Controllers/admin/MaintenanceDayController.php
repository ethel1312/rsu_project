<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Services\Vehicles\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceDayController extends Controller
{
    public function index(Request $request, MaintenanceSchedule $schedule)
    {
        if ($request->ajax()) {
            return DataTables::of($schedule->days()->select('maintenance_days.*'))
                ->addColumn('image', function ($day) {
                    if (! $day->image_path) {
                        return '<span class="text-muted">Sin imagen</span>';
                    }
                    $url = e(asset('storage/'.$day->image_path));
                    $date = e($day->date);

                    return '<a href="'.$url.'" target="_blank" rel="noopener">'
                        .'<img src="'.$url.'" alt="Evidencia del '.$date.'" width="80" height="60" style="object-fit:cover;border-radius:6px" loading="lazy"></a>';
                })
                ->addColumn('status_label', fn ($day) => $day->is_completed === null ? 'Pendiente' : ($day->is_completed ? 'Realizado' : 'No realizado'))
                ->addColumn('actions', function ($day) use ($schedule) {
                    $url = e(route('admin.maintenance-days.edit', [$schedule, $day]));

                    return '<button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-url="'.$url.'" title="Editar seguimiento" aria-label="Editar seguimiento"><i class="bi bi-pencil-square"></i></button>';
                })
                ->rawColumns(['image', 'actions'])->make(true);
        }
        $schedule->load(['maintenance', 'vehicle']);
        return view('admin.maintenances.days.index', compact('schedule'));
    }

    public function edit(MaintenanceSchedule $schedule, MaintenanceDay $day)
    {
        abort_unless($day->maintenance_schedule_id === $schedule->id, 404);
        return view('admin.maintenances.days.edit', compact('schedule', 'day'));
    }

    public function update(Request $request, MaintenanceSchedule $schedule, MaintenanceDay $day, MaintenanceService $service)
    {
        abort_unless($day->maintenance_schedule_id === $schedule->id, 404);
        $data = $request->validate([
            'observation' => ['nullable', 'string', 'max:3000'],
            'is_completed' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['observation' => 'observación', 'is_completed' => 'estado', 'image' => 'imagen']);
        $newPath = $request->hasFile('image') ? $request->file('image')->store('maintenances', 'public') : null;
        try {
            $oldPath = DB::transaction(function () use ($data, $schedule, $day, $newPath, $service) {
                $service->lockCalendar();
                $day = $schedule->days()->findOrFail($day->id);
                $oldPath = $day->image_path;
                $day->update([
                    'observation' => $data['observation'] ?? null,
                    'is_completed' => $data['is_completed'] ?? null,
                    'image_path' => $newPath ?? $oldPath,
                ]);
                return $oldPath;
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }
        if ($newPath && $oldPath) Storage::disk('public')->delete($oldPath);
        return response()->json(['message' => 'Seguimiento del mantenimiento actualizado correctamente.']);
    }
}
