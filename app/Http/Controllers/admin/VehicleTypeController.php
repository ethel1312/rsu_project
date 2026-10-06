<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use Yajra\DataTables\Facades\DataTables;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $types = VehicleType::select(['id', 'name', 'description', 'created_at', 'updated_at']);

            return DataTables::of($types)
            // Fecha arriba (dd/mm/YYYY) y hora debajo (HH:mm)
                ->editColumn('created_at', function ($color) {
                    return $this->formatDate($color->created_at);
                })
                ->editColumn('updated_at', function ($color) {
                    return $this->formatDate($color->updated_at);
                })
                ->addColumn('edit', function ($type) {
                    return '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="' . $type->id . '"><i class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn('delete', function ($type) {
                    return '<form action="' . route('admin.vehicle_types.destroy', $type->id) . '" method="POST" class="m-0 frmEliminar">' .
                           csrf_field() . method_field('DELETE') .
                           '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3-fill"></i></button></form>';
                })
                ->rawColumns(['created_at', 'updated_at','edit', 'delete'])
                ->make(true);
        }
        return view('admin.vehicle_types.index');
    }

    private function formatDate(\DateTimeInterface|string|null $value): string
    {
        if (!$value) {
            return '';
        }

        $d = Carbon::parse($value)->setTimezone('America/Lima');

        return '<div class="rsu-date">' . $d->format('d/m/Y') . '<small>' . $d->format('H:i') . '</small></div>';
    }

    private function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de vehículo es obligatorio.',
            'name.max'      => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'   => 'Ya existe un tipo de vehículo con ese nombre.',
        ];
    }

    public function create()
    {
        $vehicleType = new VehicleType();
        return view('admin.vehicle_types.create', compact('vehicleType'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|unique:vehicletypes'], $this->messages());
            VehicleType::create($request->all());
            return response()->json(['message' => 'Tipo registrado exitosamente.'], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
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
            $request->validate(['name' => 'required|unique:vehicletypes,name,' . $id], $this->messages());
            $vehicleType->update($request->all());
            return response()->json(['message' => 'Tipo actualizado exitosamente.'], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            VehicleType::find($id)->delete();
            return response()->json(['message' => 'Tipo eliminado exitosamente.'], 200);
        } catch (QueryException $e) {
            // 23000 = restricción de llave foránea: el tipo está en uso
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'No se puede eliminar: este tipo está asignado a uno o más vehículos.'
                ], 409);
            }
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error al eliminar: ' . $th->getMessage()], 500);
        }
    }
}
