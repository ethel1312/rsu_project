<?php

namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\EmployeeType;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $types = EmployeeType::select([
                'id', 
                'name', 
                'description',
                'is_default', 
                'created_at', 
                'updated_at'
                ]);

                return datatables()->of($types)
                ->editColumn('created_at', function ($type) {
                    return $this->formatDate($type->created_at);
                })
                ->editColumn('updated_at', function ($type) {
                    return $this->formatDate($type->updated_at);
                })

                ->addColumn('edit', function ($type) {
                    return '<button type="button" 
                                class="btn btn-sm btn-primary btnEditar" 
                                data-id="' . $type->id . '">
                                <i class="bi bi-pencil-square"></i>
                                </button>';
                })

                ->addColumn('delete', function ($type) {

                    if ($type->is_default) {
                        return '<span class="text-muted" title="Tipo predeterminado">
                                    <i class="bi bi-lock-fill"></i>
                                </span>';
                    }

                    return '<form action="' . route('admin.employee_types.destroy', $type->id) . '" method="POST" class="m-0 frmEliminar">' .
                        csrf_field() .
                        method_field('DELETE') .
                        '<button type="submit" class="btn btn-sm btn-danger">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                    </form>';
                })
                ->rawColumns(['created_at', 'updated_at','edit', 'delete'])
                ->make(true);
        }

        return view('admin.employees.employee_types.index');
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
            'name.required' => 'El nombre del tipo de personal es obligatorio.',
            'name.max'      => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'   => 'Ya existe un tipo de personal con ese nombre.',
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $employeeType = new EmployeeType();
        return view('admin.employees.employee_types.create', compact('employeeType'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|unique:employee_types'], 
            $this->messages());

            EmployeeType::create($request->all());

            return response()->json([
            'message' => 'Tipo registrado exitosamente.'
        ], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $employeeType = EmployeeType::find($id);
        return view('admin.employees.employee_types.edit', compact('employeeType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {

            $employeeType = EmployeeType::findOrFail($id);

            $request->validate(
                [
                    'name' => 'required|max:255|unique:employee_types,name,' . $id,
                    'description' => 'nullable'
                ],
                $this->messages()
            );

            $data = $request->all();

            // Los tipos predeterminados no pueden cambiar de nombre
            if ($employeeType->is_default) {
                $data['name'] = $employeeType->name;
            }

            $employeeType->update($data);

            return response()->json([
                'message' => 'Tipo actualizado exitosamente.'
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $th) {

            return response()->json([
                'error' => 'Error: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {

            $employeeType = EmployeeType::findOrFail($id);

            if ($employeeType->is_default) {
                return response()->json([
                    'error' => 'No se puede eliminar este tipo de personal porque es un tipo predeterminado del sistema.'
                ], 409);
            }

            $employeeType->delete();

            return response()->json([
                'message' => 'Tipo eliminado exitosamente.'
            ], 200);

        } catch (QueryException $e) {

            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'No se puede eliminar: este tipo está asignado a uno o más empleados.'
                ], 409);
            }

            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);

        } catch (\Exception $th) {

            return response()->json([
                'error' => 'Error al eliminar: ' . $th->getMessage()
            ], 500);
        }
    }
}
