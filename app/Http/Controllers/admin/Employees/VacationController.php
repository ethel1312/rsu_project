<?php


namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\Vacation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;


class VacationController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            
        $vacations = Vacation::with('employee')->select('vacations.*');

        return DataTables::of($vacations)
            ->addColumn('dni', function ($vacation) {
                return '<span class="vac-dni">' .
                    e($vacation->employee?->dni ?? '-') .
                    '</span>';
            })
            ->addColumn('employee', function ($vacation) {
                return $vacation->employee
                    ? $vacation->employee->first_name . ' ' .
                    $vacation->employee->last_name
                    : 'Personal no disponible';
            })
            ->editColumn('created_at', function ($vacation) {
                return $vacation->created_at?->format('d/m/Y') ?? '-';
            })
            ->editColumn('start_date', function ($vacation) {
                return $vacation->start_date?->format('d/m/Y') ?? '-';
            })
            ->editColumn('end_date', function ($vacation) {
                return $vacation->end_date?->format('d/m/Y') ?? '-';
            })
            
            ->addColumn('days_remaining', function ($vacation) {
                return '<span class="vac-days vac-days-remaining">-</span>';
            })

            
            ->editColumn('days_requested', function ($vacation) {
                return '<span class="vac-days">' .
                    (int) $vacation->days_requested .
                    '</span>';
            })

            ->editColumn('status', function ($vacation) {
                $classes = [
                    'Pendiente' => 'vac-status-pending',
                    'Aprobada' => 'vac-status-approved',
                    'Rechazada' => 'vac-status-rejected',
                    'Cancelado' => 'vac-status-cancelled',
                ];

                $class = $classes[$vacation->status] ?? 'vac-status-cancelled';

                return '<span class="vac-status ' . $class . '">' .
                    e($vacation->status) .
                    '</span>';
            })
            ->addColumn('actions', function ($vacation) {
                
                if ($vacation->status === 'Aprobada') {
                    return '<div class="vac-actions">
                        <button class="btn btn-success btn-sm" disabled title="Solicitud aprobada">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" disabled title="Solicitud aprobada">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <button class="btn btn-primary btn-sm" disabled title="Solicitud aprobada">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" disabled title="Solicitud aprobada">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                    </div>';
                }


                $buttons = '';

                if ($vacation->status === 'Pendiente') {
                    $buttons .= '<button type="button" class="btn btn-success btn-sm btnAprobar"
                                    data-id="'.$vacation->id.'" title="Aprobar">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm btnRechazar"
                                    data-id="'.$vacation->id.'" title="Rechazar">
                                    <i class="bi bi-x-lg"></i>
                                </button>';
                }

                $buttons .= '<button type="button" class="btn btn-primary btn-sm btnEditar"
                                data-id="'.$vacation->id.'" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>';

                $buttons .= '<form action="'.route('admin.vacations.destroy', $vacation->id).'"
                                method="POST" class="d-inline frmEliminar">' .
                                csrf_field() . method_field('DELETE') .
                                '<button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </form>';

                $buttons = '<div class="vac-actions">' . $buttons . '</div>';

                return $buttons;
            })
            ->filterColumn('employee', function ($query, $keyword) {
                $query->whereHas('employee', function ($employeeQuery) use ($keyword) {
                    $employeeQuery->where(function ($q) use ($keyword) {
                        $q->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('dni', 'like', "%{$keyword}%");
                    });
                });
            })
            ->filterColumn('dni', function ($query, $keyword) {
                $query->whereHas('employee', function ($employeeQuery) use ($keyword) {
                    $employeeQuery->where('dni', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns([
                'dni',
                'days_requested',
                'status',
                'days_remaining',
                'actions'
            ])
            ->make(true);

        }

        return view('admin.employees.vacations.index');
    }

    public function create()
    {
        $vacation = new Vacation();
        $employees = \App\Models\Employees\Employee::orderBy('first_name')
        ->get()
        ->mapWithKeys(function ($employee) {
            return [
                $employee->id => $employee->first_name . ' ' . $employee->last_name
                    . ' - ' . $employee->dni
            ];
        });

        return view('admin.employees.vacations.create', compact('vacation', 'employees'));
    }


    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        if ($this->hasDateConflict(
            $data['employee_id'],
            $data['start_date'],
            $data['end_date']
        )) {
            return response()->json([
                'error' => 'El empleado ya tiene una solicitud pendiente o aprobada que coincide con esas fechas.'
            ], 409);
        }

        // La solicitud siempre comienza pendiente.
        $data['status'] = 'Pendiente';

        Vacation::create($data);

        return response()->json([
            'message' => 'Solicitud de vacaciones registrada exitosamente.'
        ]);
    }


    public function edit(string $id)
    {
        $vacation = Vacation::findOrFail($id);

        if ($vacation->status === 'Aprobada') {
            return response('Una solicitud aprobada no puede modificarse.', 409);
        }

        $employees = \App\Models\Employees\Employee::orderBy('first_name')
            ->get()
            ->mapWithKeys(function ($employee) {
                return [
                    $employee->id => $employee->first_name . ' ' . $employee->last_name
                        . ' - ' . $employee->dni
                ];
            });

        return view('admin.employees.vacations.edit', compact('vacation', 'employees'));
    }

    public function update(Request $request, string $id)
    {
        $vacation = Vacation::findOrFail($id);

        if ($vacation->status === 'Aprobada') {
            return response()->json([
                'error' => 'No se puede modificar una solicitud de vacaciones aprobada.'
            ], 409);
        }

        $data = $this->validatedData($request);

        if ($this->hasDateConflict(
            $data['employee_id'],
            $data['start_date'],
            $data['end_date'],
            $vacation->id
        )) {
            return response()->json([
                'error' => 'El empleado ya tiene una solicitud pendiente o aprobada que coincide con esas fechas.'
            ], 409);
        }

        $vacation->update($data);

        return response()->json([
            'message' => 'Solicitud de vacaciones actualizada exitosamente.'
        ]);
    }


    public function destroy(string $id)
    {
        $vacation = Vacation::findOrFail($id);

        if ($vacation->status === 'Aprobada') {
            return response()->json([
                'error' => 'No se puede eliminar una solicitud de vacaciones aprobada.'
            ], 409);
        }

        $vacation->delete();

        return response()->json([
            'message' => 'Solicitud de vacaciones eliminada exitosamente.'
        ]);
    }

    /**
     * Comprueba si el empleado tiene otra solicitud pendiente
     * o aprobada que se cruce con el período indicado.
     */
    private function hasDateConflict(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        return Vacation::where('employee_id', $employeeId)
            ->whereIn('status', ['Pendiente', 'Aprobada'])
            ->when($excludeId, function ($query) use ($excludeId) {
                $query->where('id', '!=', $excludeId);
            })
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'days_requested' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'employee_id.required' => 'Debe seleccionar al trabajador.',
            'employee_id.exists' => 'El trabajador seleccionado no existe.',
            'days_requested.required' => 'Debe indicar los días solicitados.',
            'days_requested.integer' => 'Los días solicitados deben ser un número entero.',
            'days_requested.min' => 'Debe solicitar al menos un día.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
        ]);
    }

}
