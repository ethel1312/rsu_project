<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Programming\Zone;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $zones = Zone::select('zones.*')->latest();

            return DataTables::of($zones)
                ->addColumn('name_formatted', fn($z) => e($z->name))
                ->addColumn('district_formatted', fn($z) => e($z->district))
                ->addColumn('province_formatted', fn($z) => e($z->province))
                ->addColumn('department_formatted', fn($z) => e($z->department))
                ->addColumn('description_formatted', fn($z) => e($z->description ?? '-'))
                ->addColumn('coordinates_count', function ($z) {
                    $count = is_array($z->coordinates) ? count($z->coordinates) : 0;
                    return '<span class="badge badge-info text-white px-2 py-1" style="background:#20c997;">' . $count . ' PTS</span>';
                })
                ->addColumn('status_badge', function ($z) {
                    return $z->status
                        ? '<span class="badge-active">Activo</span>'
                        : '<span class="badge-inactive">Inactivo</span>';
                })
                ->addColumn('created_at_formatted', function ($z) {
                    return $z->created_at ? $z->created_at->format('d/m/Y H:i') : '-';
                })
                ->addColumn('actions', function ($z) {
                    return '<div class="d-flex justify-content-center">'
                        . '<button type="button" class="btn btn-sm btn-outline-primary btnEditar mr-1" data-id="' . $z->id . '" title="Editar"><i class="bi bi-pencil-square"></i></button>'
                        . '<form action="' . route('admin.programming.zones.destroy', $z->id) . '" method="POST" class="frmEliminar d-inline">'
                        . csrf_field() . method_field('DELETE')
                        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>'
                        . '</form>'
                        . '</div>';
                })
                ->rawColumns(['coordinates_count', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('admin.programming.zones.index');
    }

    public function create()
    {
        $existingZones = Zone::where('status', true)->get(['id', 'name', 'coordinates']);
        $zone = null; 
        return view('admin.programming.zones.create', compact('existingZones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'department' => 'required|string',
            'province' => 'required|string',
            'district' => 'required|string',
            'description' => 'nullable|string',
            'average_waste_kg' => 'nullable|numeric|min:0',
            'status' => 'required|boolean',
            'coordinates' => 'required|string', 
        ]);

        $coords = json_decode($request->coordinates, true);
        if (!is_array($coords) || count($coords) < 3) {
            return response()->json([
                'error' => 'Debe trazar un polígono con al menos 3 coordenadas en el mapa.'
            ], 422);
        }

        Zone::create([
            'name' => $request->name,
            'department' => $request->department,
            'province' => $request->province,
            'district' => $request->district,
            'description' => $request->description,
            'average_waste_kg' => $request->average_waste_kg,
            'status' => $request->boolean('status'),
            'coordinates' => $coords,
        ]);

        return response()->json(['message' => 'Zona registrada exitosamente.']);
    }

    public function edit(Zone $zone)
    {
        $existingZones = Zone::where('status', true)
            ->where('id', '!=', $zone->id)
            ->get(['id', 'name', 'coordinates']);

        return view('admin.programming.zones.edit', compact('zone', 'existingZones'));
    }

    public function update(Request $request, Zone $zone)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'department' => 'required|string',
            'province' => 'required|string',
            'district' => 'required|string',
            'description' => 'nullable|string',
            'average_waste_kg' => 'nullable|numeric|min:0',
            'status' => 'required|boolean',
            'coordinates' => 'required|string',
        ]);

        $coords = json_decode($request->coordinates, true);
        if (!is_array($coords) || count($coords) < 3) {
            return response()->json([
                'error' => 'El perímetro debe contener al menos 3 coordenadas.'
            ], 422);
        }

        $zone->update([
            'name' => $request->name,
            'department' => $request->department,
            'province' => $request->province,
            'district' => $request->district,
            'description' => $request->description,
            'average_waste_kg' => $request->average_waste_kg,
            'status' => $request->boolean('status'),
            'coordinates' => $coords,
        ]);

        return response()->json(['message' => 'Zona actualizada exitosamente.']);
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();
        return response()->json(['message' => 'Zona eliminada correctamente.']);
    }
}
