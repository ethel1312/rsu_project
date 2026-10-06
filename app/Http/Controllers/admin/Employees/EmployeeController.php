<?php

namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $employees = Employee::with('employeeType')
                ->select([
                    'id',
                    'dni',
                    'first_name',
                    'last_name',
                    'email',
                    'status',
                    'image_path',
                    'employee_type_id',
                    'created_at',
                    'updated_at'
                ]);

            return DataTables::of($employees)

                ->addColumn('image', function ($employee) {

                    if ($employee->image_path) {

                        $imageUrl = e(asset('storage/' . $employee->image_path));
                        $employeeName = e($employee->first_name . ' ' . $employee->last_name);

                        return '<img src="' . $imageUrl . '"
                                    class="employee-thumbnail btnVerImagenPersonal"
                                    data-image="' . $imageUrl . '"
                                    data-name="' . $employeeName . '"
                                    alt="' . $employeeName . '">';

                                            }

                    return '<div class="employee-placeholder">
                                <i class="bi bi-image"></i>
                            </div>';
                })

                ->addColumn('employee_type', function ($employee) {

                    return '<span class="badge-type">'
                        . ($employee->employeeType->name ?? 'Sin tipo')
                        . '</span>';

                })

                ->addColumn('status', function ($employee) {

                    if ($employee->status) {

                        return '<span class="badge-active">
                                    Activo
                                </span>';

                    }

                    return '<span class="badge-inactive">
                                Inactivo
                            </span>';
                })

                ->editColumn('created_at', function ($employee) {

                    return $this->formatDate($employee->created_at);

                })

                ->addColumn('edit', function ($employee) {

                    return '<button type="button"
                                class="btn btn-sm btn-primary btnEditar"
                                data-id="' . $employee->id . '">
                                <i class="bi bi-pencil-square"></i>
                            </button>';
                })

                ->addColumn('delete', function ($employee) {

                    return '<form action="' .
                        route('admin.employees.destroy', $employee->id) .
                        '" method="POST" class="m-0 frmEliminar">' .

                        csrf_field() .
                        method_field('DELETE') .

                        '<button type="submit"
                                class="btn btn-sm btn-danger">
                                <i class="bi bi-trash3-fill"></i>
                            </button>

                            </form>';
                })

                ->rawColumns([
                    'image',
                    'employee_type',
                    'status',
                    'created_at',
                    'edit',
                    'delete'
                ])

                ->make(true);
        }

        return view('admin.employees.employees.index');
    }


    private function formatDate(\DateTimeInterface|string|null $value): string
    {
        if (!$value) {
            return '';
        }

        $d = Carbon::parse($value)
            ->setTimezone('America/Lima');

        return '<div class="rsu-date">'
            . $d->format('d/m/Y')
            . '<small>'
            . $d->format('H:i')
            . '</small></div>';
    }


    private function messages(): array
    {
        return [

            'dni.required' =>
                'El DNI es obligatorio.',

            'dni.digits' =>
                'El DNI debe tener exactamente 8 dígitos.',

            'dni.unique' =>
                'Ya existe un personal con ese DNI.',

            'first_name.required' =>
                'Los nombres son obligatorios.',

            'last_name.required' =>
                'Los apellidos son obligatorios.',

            'birth_date.required' =>
                'La fecha de nacimiento es obligatoria.',

            'phone.digits' =>
                'El teléfono debe tener exactamente 9 dígitos.',

            'email.required' =>
                'El correo electrónico es obligatorio.',

            'email.email' =>
                'Ingrese un correo electrónico válido.',
            
            'email.regex' =>
                'Ingrese un correo electrónico válido, por ejemplo: usuario@gmail.com',

            'email.unique' =>
                'Ya existe un personal registrado con ese correo.',

            'employee_type_id.required' =>
                'El tipo de personal es obligatorio.',

            'address.required' =>
                'La dirección es obligatoria.',

            'password.required' =>
                'La contraseña es obligatoria.',

            'password.min' =>
                'La contraseña debe tener mínimo 6 caracteres.',

            'image_path.image' =>
                'El archivo debe ser una imagen.',

            'image_path.mimes' =>
                'La imagen debe ser JPG, JPEG o PNG.',
        ];
    }


    public function create()
    {
        $employee = new Employee();

        $employeeTypes = EmployeeType::pluck('name', 'id');

        return view(
            'admin.employees.employees.create',
            compact('employee', 'employeeTypes')
        );
    }


    public function store(Request $request)
    {
        try {

            $request->validate([

                'dni' => 'required|digits:8|unique:employees,dni',

                'first_name' => 'required',

                'last_name' => 'required',

                'birth_date' => 'required|date',

                'phone' => 'nullable|digits:9',

                'email' => [
                    'required',
                    'email',
                    'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
                    'unique:employees,email'
                ],

                'status' => 'required',

                'password' => 'required|min:6',

                'address' => 'required',

                'employee_type_id' => 'required|exists:employee_types,id',

                'image_path' => 'nullable|image|mimes:jpg,jpeg,png'

            ], $this->messages());


            $data = $request->all();


            if ($request->hasFile('image_path')) {

                $data['image_path'] =
                    $request->file('image_path')
                        ->store('employees', 'public');
            }


            Employee::create($data);


            return response()->json([
                'message' => 'Personal registrado exitosamente.'
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


    public function edit(string $id)
    {
        $employee = Employee::find($id);

        $employeeTypes = EmployeeType::pluck('name', 'id');

        return view(
            'admin.employees.employees.edit',
            compact('employee', 'employeeTypes')
        );
    }


    public function update(Request $request, string $id)
    {
        try {

            $employee = Employee::find($id);


            $request->validate([

                'dni' =>
                    'required|digits:8|unique:employees,dni,' . $id,

                'first_name' =>
                    'required',

                'last_name' =>
                    'required',

                'birth_date' =>
                    'required|date',

                'phone' =>
                    'nullable|digits:9',

                'email' => [
                    'required',
                    'email',
                    'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
                    'unique:employees,email,' . $id
                ],

                'status' =>
                    'required',

                'password' => 'nullable|min:6',

                'address' =>
                    'required',

                'employee_type_id' =>
                    'required|exists:employee_types,id',

                'image_path' =>
                    'nullable|image|mimes:jpg,jpeg,png'

            ], $this->messages());


            $data = $request->all();

            if (empty($data['password'])) {
                unset($data['password']);
            }

            if ($request->hasFile('image_path')) {

                $data['image_path'] =
                    $request->file('image_path')
                        ->store('employees', 'public');
            }


            $employee->update($data);


            return response()->json([
                'message' => 'Personal actualizado exitosamente.'
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


    public function destroy(string $id)
    {
        try {

            Employee::find($id)->delete();

            return response()->json([
                'message' => 'Personal eliminado exitosamente.'
            ], 200);


        } catch (QueryException $e) {

            return response()->json([
                'error' => 'No se puede eliminar el personal porque tiene registros asociados.'
            ], 409);


        } catch (\Exception $th) {

            return response()->json([
                'error' => 'Error al eliminar: ' . $th->getMessage()
            ], 500);
        }
    }
}