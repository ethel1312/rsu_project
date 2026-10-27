<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                    return '<div class="d-flex justify-content-center" style="gap:6px">'
                        . '<button type="button" class="btn btn-sm btn-zone-map btnVerMapaZona" data-map-url="' . route('admin.programming.zones.map-data', $z->id) . '" data-zone-name="' . e($z->name) . '" title="Ver mapa de la zona" aria-label="Ver mapa de la zona"><i class="bi bi-map"></i></button>'
                        . '<button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="' . $z->id . '" title="Editar"><i class="bi bi-pencil-square"></i></button>'
                        . '<form action="' . route('admin.programming.zones.destroy', $z->id) . '" method="POST" class="frmEliminar d-inline">'
                        . csrf_field() . method_field('DELETE')
                        . '<button type="submit" class="btn btn-sm btn-danger" title="Eliminar zona" aria-label="Eliminar zona"><i class="bi bi-trash3-fill"></i></button>'
                        . '</form>'
                        . '</div>';
                })
                ->rawColumns(['coordinates_count', 'status_badge', 'actions'])
                ->make(true);
        }

        return view('admin.programming.zones.index');
    }

    public function map(Request $request)
    {
        $selectedZone = null;
        $zonesQuery = Zone::query()->orderBy('name');
        if ($request->filled('zone_id')) {
            $selectedZone = Zone::findOrFail($request->integer('zone_id'));
            $zonesQuery->whereKey($selectedZone->id);
        }

        $mapZones = $zonesQuery->get()->map(function (Zone $zone) {
            $coordinates = $zone->coordinates;
            $valid = is_array($coordinates) && count($coordinates) >= 3;
            $points = [];

            if ($valid) {
                foreach ($coordinates as $coordinate) {
                    if (!is_array($coordinate)
                        || !isset($coordinate['lat'], $coordinate['lng'])
                        || !is_numeric($coordinate['lat'])
                        || !is_numeric($coordinate['lng'])) {
                        $valid = false;
                        break;
                    }

                    $latitude = (float) $coordinate['lat'];
                    $longitude = (float) $coordinate['lng'];
                    if (!is_finite($latitude) || !is_finite($longitude)
                        || abs($latitude) > 90 || abs($longitude) > 180) {
                        $valid = false;
                        break;
                    }

                    $points[] = [$latitude, $longitude];
                }
            }

            if ($valid && count(array_unique(array_map('json_encode', $points))) < 3) {
                $valid = false;
            }

            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'district' => $zone->district,
                'province' => $zone->province,
                'department' => $zone->department,
                'description' => $zone->description,
                'average_waste_kg' => $zone->average_waste_kg,
                'status' => $zone->status,
                'coordinates' => $valid ? $points : [],
                'has_valid_coordinates' => $valid,
            ];
        })->values();

        return view('admin.programming.zones.map', compact('mapZones', 'selectedZone'));
    }

    public function mapData(Zone $zone)
    {
        $coordinates = $zone->coordinates;
        $valid = is_array($coordinates) && count($coordinates) >= 3;
        $points = [];

        if ($valid) {
            foreach ($coordinates as $coordinate) {
                if (!is_array($coordinate)
                    || !isset($coordinate['lat'], $coordinate['lng'])
                    || !is_numeric($coordinate['lat'])
                    || !is_numeric($coordinate['lng'])) {
                    $valid = false;
                    break;
                }

                $latitude = (float) $coordinate['lat'];
                $longitude = (float) $coordinate['lng'];
                if (!is_finite($latitude) || !is_finite($longitude)
                    || abs($latitude) > 90 || abs($longitude) > 180) {
                    $valid = false;
                    break;
                }

                $points[] = [$latitude, $longitude];
            }
        }

        if ($valid && count(array_unique(array_map('json_encode', $points))) < 3) {
            $valid = false;
        }

        return response()->json([
            'name' => $zone->name,
            'district' => $zone->district,
            'province' => $zone->province,
            'department' => $zone->department,
            'status' => $zone->status,
            'coordinates' => $valid ? $points : [],
            'has_valid_coordinates' => $valid,
        ]);
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

        // Dentro de store() y update() luego de validar $coords:
        $query = Zone::where('status', true);
        if (isset($zone)) {
            $query->where('id', '!=', $zone->id);
        }
        $otherZones = $query->get();

        foreach ($otherZones as $existing) {
            $exCoords = is_array($existing->coordinates) ? $existing->coordinates : json_decode($existing->coordinates, true);
            if (is_array($exCoords) && count($exCoords) >= 3) {
                if ($this->polygonsOverlap($coords, $exCoords)) {
                    return response()->json([
                        'error' => "El perímetro se cruza o invade la zona '{$existing->name}'."
                    ], 422);
                }
            }
        }

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

        // Dentro de store() y update() luego de validar $coords:
        $query = Zone::where('status', true);
        if (isset($zone)) {
            $query->where('id', '!=', $zone->id);
        }
        $otherZones = $query->get();

        foreach ($otherZones as $existing) {
            $exCoords = is_array($existing->coordinates) ? $existing->coordinates : json_decode($existing->coordinates, true);
            if (is_array($exCoords) && count($exCoords) >= 3) {
                if ($this->polygonsOverlap($coords, $exCoords)) {
                    return response()->json([
                        'error' => "El perímetro se cruza o invade la zona '{$existing->name}'."
                    ], 422);
                }
            }
}

        return response()->json(['message' => 'Zona actualizada exitosamente.']);
    }

    public function destroy(Zone $zone)
    {
        if (Schema::hasTable('personnel_groups')
            && DB::table('personnel_groups')->where('zone_id', $zone->id)->exists()) {
            return response()->json([
                'error' => 'Esta zona pertenece a un grupo de personal. Cambie la zona del grupo o desactive la zona para conservar sus datos.',
            ], 422);
        }

        $zone->delete();
        return response()->json(['message' => 'Zona eliminada correctamente.']);
    }

    /**
 * Verifica si dos polígonos tienen intersección (Ray Casting / Segment Intersection)
 */
    private function polygonsOverlap(array $polyA, array $polyB): bool
    {
        // Función auxiliar para determinar si dos segmentos se cruzan
        $ccw = function($p1, $p2, $p3) {
            return ($p3['lat'] - $p1['lat']) * ($p2['lng'] - $p1['lng']) > ($p2['lat'] - $p1['lat']) * ($p3['lng'] - $p1['lng']);
        };

        $intersect = function($p1, $p2, $p3, $p4) use ($ccw) {
            return ($ccw($p1, $p3, $p4) != $ccw($p2, $p3, $p4)) && ($ccw($p1, $p2, $p3) != $ccw($p1, $p2, $p4));
        };

        $countA = count($polyA);
        $countB = count($polyB);

        // Revisar cruce de aristas
        for ($i = 0; $i < $countA; $i++) {
            $a1 = $polyA[$i];
            $a2 = $polyA[($i + 1) % $countA];

            for ($j = 0; $j < $countB; $j++) {
                $b1 = $polyB[$j];
                $b2 = $polyB[($j + 1) % $countB];

                if ($intersect($a1, $a2, $b1, $b2)) {
                    return true;
                }
            }
        }

        return false;
    }
}
