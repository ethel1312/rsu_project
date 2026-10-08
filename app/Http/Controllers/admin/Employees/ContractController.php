<?php

namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Contract;
use App\Models\Employees\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $contracts = Contract::with(['employee.employeeType'])
                ->select('contracts.*')
                ->orderBy('contracts.created_at', 'desc');

            return DataTables::of($contracts)
                ->addColumn('dni', function ($c) {
                    return $c->employee->dni ?? 'N/A';
                })
                ->addColumn('employee_name', function ($c) {
                    return $c->employee->full_name ?: ($c->employee->first_name ?? 'N/A');
                })
                ->addColumn('contract_type_badge', function ($c) {
                    return '<span class="badge-type">' . e($c->contract_type) . '</span>';
                })
                ->addColumn('start_date_formatted', function ($c) {
                    return $c->start_date ? $c->start_date->format('d/m/Y') : '-';
                })
                ->addColumn('end_date_formatted', function ($c) {
                    return $c->end_date ? $c->end_date->format('d/m/Y') : '-';
                })
                ->addColumn('salary_formatted', function ($c) {
                    return 'S/ ' . number_format($c->salary, 2);
                })
                ->addColumn('position', function ($c) {
                    $typeName = $c->employee->employeeType->name ?? 'Sin asignar';
                    return '<span class="badge-type">' . e(strtoupper($typeName)) . '</span>';
                })
                ->addColumn('status_badge', function ($c) {
                    return $c->is_active
                        ? '<span class="badge-active">Activo</span>'
                        : '<span class="badge-inactive">Inactivo</span>';
                })
                ->addColumn('edit', function ($c) {
                    return '<button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="' . $c->id . '" title="Editar">'
                        . '<i class="bi bi-pencil-square"></i>'
                        . '</button>';
                })
                ->addColumn('delete', function ($c) {
                    return '<form action="' . route('admin.contracts.destroy', $c->id) . '" method="POST" class="frmEliminar d-inline">'
                        . csrf_field() . method_field('DELETE')
                        . '<button type="submit" class="btn btn-sm btn-danger" title="Eliminar">'
                        . '<i class="bi bi-trash3-fill"></i>'
                        . '</button>'
                        . '</form>';
                })
                ->rawColumns(['contract_type_badge', 'position', 'status_badge', 'edit', 'delete'])
                ->make(true);
        }

        return view('admin.employees.contracts.index');
    }

    private function messages()
    {
        return [
            'employee_id.required' => 'El empleado es obligatorio.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',

            'contract_type.required' => 'El tipo de contrato es obligatorio.',
            'contract_type.in' => 'El tipo de contrato seleccionado no es válido.',

            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',

            'end_date.required_if' => 'La fecha de fin es obligatoria para los contratos temporales.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',

            'salary.required' => 'El salario es obligatorio.',
            'salary.numeric' => 'El salario debe ser un valor numérico.',
            'salary.min' => 'El salario no puede ser negativo.',

            'trial_period_months.integer' => 'El período de prueba debe ser un número entero.',
            'trial_period_months.min' => 'El período de prueba no puede ser negativo.',
        ];
    }

    private function hasOverlappingTemporaryContract(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $exceptContractId = null
    ): bool {
        $query = Contract::where('employee_id', $employeeId)
            ->where('contract_type', 'Temporal')
            ->where('start_date', '<=', $endDate->toDateString())
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate->toDateString());
            });

        if ($exceptContractId !== null) {
            $query->where('id', '!=', $exceptContractId);
        }

        return $query->exists();
    }

    public function create()
    {
        return view('admin.employees.contracts.create', ['employees' => []]);
    }

    public function searchEmployees(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100000',
        ]);
        $term = trim($data['q'] ?? '');
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $query = Employee::where('status', true);
        foreach (preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $word = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word);
            $query->where(function ($query) use ($word) {
                $query->whereRaw("dni LIKE ? ESCAPE '!'", [$word.'%'])
                    ->orWhereRaw("first_name LIKE ? ESCAPE '!'", ['%'.$word.'%'])
                    ->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%'.$word.'%']);
            });
        }

        $employees = $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->simplePaginate(20, ['id', 'dni', 'first_name', 'last_name'], 'page', $data['page'] ?? 1);

        return response()->json([
            'results' => $employees->getCollection()->map(fn ($employee) => [
                'id' => $employee->id, 'text' => $this->employeeLabel($employee),
            ])->values(),
            'pagination' => ['more' => $employees->hasMorePages()],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|in:Permanente,Nombrado,Temporal',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|required_if:contract_type,Temporal|after:start_date',
            'salary' => 'required|numeric|min:0',
            'trial_period_months' => 'nullable|integer|min:0',
        ], $this->messages());

        $employeeId = $request->employee_id;
        $type = $request->contract_type;
        $startDate = Carbon::parse($request->start_date);
        $endDate = $type === 'Temporal' ? Carbon::parse($request->end_date) : null;
        $isActive = $request->boolean('is_active', true);

        // Regla 1: Permanente o Nombrado solo 1 contrato
        if (in_array($type, ['Permanente', 'Nombrado'])) {
            if (Contract::where('employee_id', $employeeId)->exists()) {
                return response()->json([
                    'error' => 'El empleado ya cuenta con un contrato registrado. Los permanentes o nombrados solo pueden tener un contrato.'
                ], 422);
            }
        } else {
            if (Contract::where('employee_id', $employeeId)->whereIn('contract_type', ['Permanente', 'Nombrado'])->exists()) {
                return response()->json([
                    'error' => 'El empleado ya es permanente/nombrado; no se le puede asignar contrato temporal.'
                ], 422);
            }
        }

        // Los temporales pueden estar activos a la vez si sus períodos no se cruzan.
        if ($isActive && $type !== 'Temporal' && Contract::where('employee_id', $employeeId)->where('is_active', true)->exists()) {
            return response()->json([
                'error' => 'El empleado ya tiene un contrato activo actualmente.'
            ], 422);
        }

        // Regla 3: No solapamiento en temporales
        if ($type === 'Temporal') {
            if ($this->hasOverlappingTemporaryContract($employeeId, $startDate, $endDate)) {
                return response()->json([
                    'error' => 'Las fechas seleccionadas se cruzan con un contrato previo de este empleado.'
                ], 422);
            }

            // Regla 4: Deben pasar al menos dos meses entre contratos temporales.
            $lastContract = Contract::where('employee_id', $employeeId)
                ->where('contract_type', 'Temporal')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', $startDate)
                ->orderBy('end_date', 'desc')
                ->first();

            if ($lastContract && $startDate->lt($lastContract->end_date->copy()->addMonthsNoOverflow(2))) {
                return response()->json([
                    'error' => 'Deben pasar al menos 2 meses desde el fin del contrato anterior para iniciar uno nuevo.'
                ], 422);
            }
        }

        Contract::create([
            'employee_id' => $employeeId,
            'contract_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'salary' => $request->salary,
            'trial_period_months' => $request->trial_period_months,
            'is_active' => $isActive,
        ]);

        return response()->json(['message' => 'Contrato registrado exitosamente.']);
    }

    public function edit(Contract $contract)
    {
        $employee = $contract->employee()->first(['id', 'dni', 'first_name', 'last_name']);
        $employees = $employee ? [$employee->id => $this->employeeLabel($employee)] : [];

        return view('admin.employees.contracts.edit', compact('contract', 'employees'));
    }

    private function employeeLabel(Employee $employee): string
    {
        return $employee->dni.' - '.$employee->last_name.', '.$employee->first_name;
    }

    public function update(Request $request, Contract $contract)
    {
        $request->validate(
            [
                'employee_id' => 'required|exists:employees,id',
                'contract_type' => 'required|in:Permanente,Nombrado,Temporal',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|required_if:contract_type,Temporal|after:start_date',
                'salary' => 'required|numeric|min:0',
                'trial_period_months' => 'nullable|integer|min:0',
            ],
            $this->messages()
        );

        $employeeId = $request->employee_id;
        $type = $request->contract_type;
        $startDate = Carbon::parse($request->start_date);
        $endDate = $type === 'Temporal' ? Carbon::parse($request->end_date) : null;
        $isActive = $request->boolean('is_active', false);

        // 1. REGLA: Permanente/Nombrado solo 1 contrato en su historia
        if (in_array($type, ['Permanente', 'Nombrado'])) {
            $hasOtherContract = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->exists();

            if ($hasOtherContract) {
                return response()->json([
                    'error' => 'El empleado ya cuenta con otro contrato registrado. Los permanentes o nombrados solo pueden tener un contrato.'
                ], 422);
            }
        } else {
            $hasPermanent = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->whereIn('contract_type', ['Permanente', 'Nombrado'])
                ->exists();

            if ($hasPermanent) {
                return response()->json([
                    'error' => 'El empleado ya tiene la condición de permanente/nombrado; no se le puede asignar contrato temporal.'
                ], 422);
            }
        }

        // Los temporales pueden estar activos a la vez si sus períodos no se cruzan.
        if ($isActive && $type !== 'Temporal') {
            $otherActiveExists = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('is_active', true)
                ->exists();

            if ($otherActiveExists) {
                return response()->json([
                    'error' => 'El empleado ya tiene otro contrato activo actualmente. Desactívelo antes de activar este.'
                ], 422);
            }
        }

        // Reglas para contratos Temporales
        if ($type === 'Temporal') {
            // 3. REGLA: No solapamiento de fechas con otros contratos del mismo empleado
            if ($this->hasOverlappingTemporaryContract($employeeId, $startDate, $endDate, $contract->id)) {
                return response()->json([
                    'error' => 'Las fechas seleccionadas se cruzan con otro contrato registrado de este empleado.'
                ], 422);
            }

            // 4. REGLA: Deben pasar al menos dos meses entre contratos temporales.
            $previousContract = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('contract_type', 'Temporal')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', $startDate)
                ->orderBy('end_date', 'desc')
                ->first();

            if ($previousContract && $startDate->lt($previousContract->end_date->copy()->addMonthsNoOverflow(2))) {
                return response()->json([
                    'error' => 'Deben pasar al menos 2 meses desde el fin del contrato anterior para iniciar uno nuevo.'
                ], 422);
            }

            // 5. REGLA: Deben pasar al menos dos meses antes del siguiente contrato.
            $nextContract = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('contract_type', 'Temporal')
                ->where('start_date', '>=', $endDate)
                ->orderBy('start_date', 'asc')
                ->first();

            if ($nextContract && $endDate->copy()->addMonthsNoOverflow(2)->gt($nextContract->start_date)) {
                return response()->json([
                    'error' => 'Deben pasar al menos 2 meses entre el fin de este contrato y el inicio del siguiente.'
                ], 422);
            }
        }

        $contract->update([
            'employee_id' => $employeeId,
            'contract_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'salary' => $request->salary,
            'trial_period_months' => $request->trial_period_months,
            'is_active' => $isActive,
        ]);

        return response()->json(['message' => 'Contrato actualizado exitosamente.']);
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();
        return response()->json(['message' => 'Contrato eliminado correctamente.']);
    }
}