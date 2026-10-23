<?php


namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\Vacation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Employees\VacationBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


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

                $year = Carbon::parse($vacation->start_date)->year;

                $balance = VacationBalance::where(
                    'employee_id',
                    $vacation->employee_id
                )
                ->where('year', $year)
                ->first();

                if (!$balance) {
                    return '<span class="vac-days">Sin saldo</span>';
                }

                $available = $balance->days_entitled - $balance->days_taken;

                return '<span class="vac-days vac-days-remaining">'
                    . $available .
                    '</span>';
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

    public function approve(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $vacation = Vacation::whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($vacation->status !== 'Pendiente') {
                    abort(409, 'La solicitud ya fue procesada.');
                }

                $year = Carbon::parse($vacation->start_date)->year;

                $balance = VacationBalance::where('employee_id', $vacation->employee_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$balance) {
                    abort(422, 'El trabajador no tiene un saldo de vacaciones registrado para ese año.');
                }

                $available = $balance->days_entitled - $balance->days_taken;

                if ($vacation->days_requested > $available) {
                    abort(422, 'El trabajador no tiene suficientes días disponibles.');
                }

                $balance->increment('days_taken', $vacation->days_requested);

                $vacation->update([
                    'status' => 'Aprobada',
                ]);
            });

            return response()->json([
                'message' => 'Solicitud aprobada y saldo actualizado correctamente.',
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'No se pudo aprobar la solicitud.',
            ], 500);
        }
    }

    public function reject(string $id)
    {
        $vacation = Vacation::findOrFail($id);

        if ($vacation->status !== 'Pendiente') {
            return response()->json([
                'error' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 409);
        }

        $vacation->update([
            'status' => 'Rechazada',
        ]);

        return response()->json([
            'message' => 'Solicitud rechazada correctamente.',
        ]);
    }

    public function create()
    {
        $vacation = new Vacation();
        $employees = [];

        return view('admin.employees.vacations.create', compact('vacation', 'employees'));
    }

    public function balanceSummary(Request $request, string $employeeId)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:1900|max:2200',
        ]);

        $employee = Employee::whereKey($employeeId)
            ->where('status', true)
            ->whereHas('contracts', function ($query) {
                $query->whereIn('contract_type', ['Permanente', 'Nombrado'])
                    ->where('is_active', true);
            })
            ->firstOrFail();

        $balance = VacationBalance::where('employee_id', $employee->id)
            ->where('year', $data['year'])
            ->first();

        return response()->json([
            'year' => (int) $data['year'],
            'has_balance' => $balance !== null,
            'days_entitled' => (int) ($balance?->days_entitled ?? 0),
            'days_taken' => (int) ($balance?->days_taken ?? 0),
            'days_available' => (int) ($balance?->days_available ?? 0),
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100000',
        ]);
        $term = trim($data['q'] ?? '');

        if (mb_strlen($term) < 2) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $query = Employee::query()
            ->where('status', true)
            ->whereHas('contracts', function ($query) {
                $query->whereIn('contract_type', ['Permanente', 'Nombrado'])
                    ->where('is_active', true);
            });

        foreach (preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $word = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word);
            $query->where(function ($query) use ($word) {
                $query->whereRaw("dni LIKE ? ESCAPE '!'", [$word . '%'])
                    ->orWhereRaw("first_name LIKE ? ESCAPE '!'", ['%' . $word . '%'])
                    ->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%' . $word . '%']);
            });
        }

        $employees = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->simplePaginate(20, ['id', 'dni', 'first_name', 'last_name'], 'page', $data['page'] ?? 1);

        return response()->json([
            'results' => $employees->getCollection()->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'text' => $employee->first_name . ' '
                        . $employee->last_name . ' - '
                        . $employee->dni,
                ];
            })->values(),
            'pagination' => ['more' => $employees->hasMorePages()],
        ]);
    }


    public function store(Request $request)
    {
        $conflict = DB::transaction(function () use ($request) {
            $data = $this->validatedData($request);

            if ($this->hasDateConflict(
                $data['employee_id'],
                $data['start_date'],
                $data['end_date']
            )) {
                return true;
            }

            $data['status'] = 'Pendiente';
            Vacation::create($data);

            return false;
        });

        if ($conflict) {
            return response()->json([
                'error' => 'El empleado ya tiene una solicitud pendiente o aprobada que coincide con esas fechas.'
            ], 409);
        }

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

        $employees = \App\Models\Employees\Employee::whereHas('contracts', function ($query) {
            $query->whereIn('contract_type', ['Permanente', 'Nombrado'])
                ->where('is_active', true);
        })
        ->where('status', true)
        ->orderBy('first_name')
        ->get()
        ->unique('id')
        ->mapWithKeys(function ($employee) {
            return [
                $employee->id => $employee->first_name . ' ' .
                    $employee->last_name . ' - ' . $employee->dni
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

        $conflict = DB::transaction(function () use ($request, $vacation) {
            $data = $this->validatedData($request);

            if ($this->hasDateConflict(
                $data['employee_id'],
                $data['start_date'],
                $data['end_date'],
                $vacation->id
            )) {
                return true;
            }

            $vacation->update($data);

            return false;
        });

        if ($conflict) {
            return response()->json([
                'error' => 'El empleado ya tiene una solicitud pendiente o aprobada que coincide con esas fechas.'
            ], 409);
        }

        return response()->json([
            'message' => 'Solicitud de vacaciones actualizada exitosamente.'
        ]);
    }


    public function destroy(string $id)
    {
        DB::transaction(function () use ($id) {
            $vacation = Vacation::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($vacation->status === 'Aprobada') {
                $year = Carbon::parse($vacation->start_date)->year;

                $balance = VacationBalance::where('employee_id', $vacation->employee_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($balance->days_taken < $vacation->days_requested) {
                    abort(409, 'No se puede devolver el saldo porque los días registrados no coinciden.');
                }

                $balance->decrement('days_taken', $vacation->days_requested);
            }

            $vacation->delete();
        });

        return response()->json([
            'message' => 'Solicitud eliminada correctamente.',
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

        $employee = \App\Models\Employees\Employee::whereKey(
            $request->input('employee_id')
        )
        ->where('status', true)
        ->whereHas('contracts', function ($query) {
            $query->whereIn('contract_type', ['Permanente', 'Nombrado'])
                ->where('is_active', true);
        })
        ->first();

        if (!$employee) {
            abort(422, 'Solo el personal activo con contrato Permanente o Nombrado puede solicitar vacaciones.');
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date|after_or_equal:today',
            'days_requested' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ], [
            'employee_id.required' => 'Debe seleccionar al trabajador.',
            'employee_id.exists' => 'El trabajador seleccionado no existe.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o una fecha futura.',
            'days_requested.required' => 'Debe ingresar la cantidad de días solicitados.',
            'days_requested.integer' => 'Los días solicitados deben ser un número entero.',
            'days_requested.min' => 'Debe solicitar al menos un día de vacaciones.',
        ]);

        $balance = VacationBalance::where('employee_id', $employee->id)
            ->where('year', Carbon::parse($data['start_date'])->year)
            ->lockForUpdate()
            ->first();
        $available = (int) ($balance?->days_available ?? 0);

        if ($data['days_requested'] > $available) {
            $dayLabel = $available === 1
                ? 'día de vacaciones disponible.'
                : 'días de vacaciones disponibles.';

            throw ValidationException::withMessages([
                'days_requested' => "Solo tienes {$available} {$dayLabel}",
            ]);
        }

        $data['end_date'] = Carbon::parse($data['start_date'])
            ->addDays($data['days_requested'] - 1)
            ->toDateString();

        return $data;
    }

}
