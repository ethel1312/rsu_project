<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $types = VehicleType::select(['id', 'name', 'description', 'created_at', 'updated_at']);

            return DataTables::of($types)
                ->addColumn('edit', function ($type) {
                    return '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="' . $type->id . '"><i class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn('delete', function ($type) {
                    return '<form action="' . route('admin.vehicle_types.destroy', $type->id) . '" method="POST" class="m-0 frmEliminar">' .
                           csrf_field() . method_field('DELETE') .
                           '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3-fill"></i></button></form>';
                })
                ->rawColumns(['edit', 'delete'])
                ->make(true);
        }
        return view('admin.vehicle_types.index');
    }

    public function create()
    {
        $vehicleType = new VehicleType();
        return view('admin.vehicle_types.create', compact('vehicleType'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|unique:vehicletypes']);
            VehicleType::create($request->all());
            return response()->json(['message' => 'Tipo registrado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function edit(string $id)
    {
        $vehicleType = VehicleType::find($id);
        return view('admin.vehicle_types.edit', compact('vehicleType'));
    }

    public function update(Request $request, string $id)
    {
        try {
            $vehicleType = VehicleType::find($id);
            $request->validate(['name' => 'required|unique:vehicletypes,name,' . $id]);
            $vehicleType->update($request->all());
            return response()->json(['message' => 'Tipo actualizado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            VehicleType::find($id)->delete();
            return response()->json(['message' => 'Tipo eliminado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error al eliminar: ' . $th->getMessage()], 500);
        }
    }
}
