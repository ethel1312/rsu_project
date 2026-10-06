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
                        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">'
                        . '<i class="bi bi-trash"></i>'
                        . '</button>'
                        . '</form>';
                })
                ->rawColumns(['contract_type_badge', 'position', 'status_badge', 'edit', 'delete'])
                ->make(true);
        }

        return view('admin.employees.contracts.index');
    }

    public function create()
    {
        $employees = Employee::where('status', true)->get()->mapWithKeys(function ($emp) {
            $name = $emp->full_name ?: ($emp->first_name ?? 'Empleado');
            return [$emp->id => "{$name} - {$emp->dni}"];
        });

        return view('admin.employees.contracts.create', compact('employees'));
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
        ]);

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

        // Regla 2: No 2 activos a la vez
        if ($isActive && Contract::where('employee_id', $employeeId)->where('is_active', true)->exists()) {
            return response()->json([
                'error' => 'El empleado ya tiene un contrato activo actualmente.'
            ], 422);
        }

        // Regla 3: No solapamiento en temporales
        if ($type === 'Temporal') {
            $overlap = Contract::where('employee_id', $employeeId)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                      });
                })->exists();

            if ($overlap) {
                return response()->json([
                    'error' => 'Las fechas seleccionadas se cruzan con un contrato previo de este empleado.'
                ], 422);
            }

            // Regla 4: No consecutivo inmediato al día siguiente
            $lastContract = Contract::where('employee_id', $employeeId)
                ->where('contract_type', 'Temporal')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', $startDate)
                ->orderBy('end_date', 'desc')
                ->first();

            if ($lastContract && $lastContract->end_date->diffInDays($startDate) <= 1) {
                return response()->json([
                    'error' => 'Para evitar estabilidad laboral, debe existir un período de corte previo.'
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
        $employees = Employee::where('status', true)->get()->mapWithKeys(function ($emp) {
            $name = $emp->full_name ?: ($emp->first_name ?? 'Empleado');
            return [$emp->id => "{$name} - {$emp->dni}"];
        });

        return view('admin.employees.contracts.edit', compact('contract', 'employees'));
    }

    public function update(Request $request, Contract $contract)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|in:Permanente,Nombrado,Temporal',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|required_if:contract_type,Temporal|after:start_date',
            'salary' => 'required|numeric|min:0',
            'trial_period_months' => 'nullable|integer|min:0',
        ]);

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

        // 2. REGLA: No 2 contratos activos a la vez
        if ($isActive) {
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
            $overlap = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
                })->exists();

            if ($overlap) {
                return response()->json([
                    'error' => 'Las fechas seleccionadas se cruzan con otro contrato registrado de este empleado.'
                ], 422);
            }

            // 4. REGLA: Margen de corte (evitar que comience inmediatamente al día siguiente de uno previo)
            $previousContract = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('contract_type', 'Temporal')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', $startDate)
                ->orderBy('end_date', 'desc')
                ->first();

            if ($previousContract && $previousContract->end_date->diffInDays($startDate) <= 1) {
                return response()->json([
                    'error' => 'Para evitar estabilidad laboral, debe existir un período de corte con el contrato previo (no puede iniciar al día siguiente).'
                ], 422);
            }

            // 5. REGLA: Margen de corte con contrato posterior (si editas la fecha fin)
            $nextContract = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('contract_type', 'Temporal')
                ->where('start_date', '>=', $endDate)
                ->orderBy('start_date', 'asc')
                ->first();

            if ($nextContract && $endDate->diffInDays($nextContract->start_date) <= 1) {
                return response()->json([
                    'error' => 'La fecha de fin deja este contrato pegado al día siguiente del próximo contrato registrado.'
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