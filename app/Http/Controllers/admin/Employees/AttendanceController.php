<?php

namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Services\Employees\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendances) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date' => [$request->ajax() ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'end_date' => [$request->ajax() ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'employee' => 'nullable|string|max:100',
        ], [
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date_format' => 'Ingrese una fecha de inicio válida.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.date_format' => 'Ingrese una fecha de fin válida.',
            'end_date.after_or_equal' => 'La fecha de inicio no puede ser mayor que la fecha de fin.',
            'employee.max' => 'La búsqueda no debe superar los 100 caracteres.',
        ]);
        $today = now(Attendance::TIMEZONE)->format('Y-m-d');
        $startDate = $data['start_date'] ?? $today;
        $endDate = $data['end_date'] ?? $today;

        if ($request->ajax()) {
            $records = Attendance::query()->join('employees', 'employees.id', '=', 'attendances.employee_id')
                ->whereBetween('attendances.date', [$startDate, $endDate])
                ->select('attendances.*', 'employees.dni', 'employees.first_name', 'employees.last_name');

            $employee = trim($data['employee'] ?? '');
            foreach (preg_split('/\s+/u', $employee, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $word = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word);
                $records->where(function ($query) use ($word) {
                    $query->whereRaw("employees.dni LIKE ? ESCAPE '!'", ['%'.$word.'%'])
                        ->orWhereRaw("employees.first_name LIKE ? ESCAPE '!'", ['%'.$word.'%'])
                        ->orWhereRaw("employees.last_name LIKE ? ESCAPE '!'", ['%'.$word.'%']);
                });
            }

            return DataTables::of($records)
                ->editColumn('date', fn ($record) => $record->date->format('d/m/Y'))
                ->editColumn('type', fn ($record) => match ($record->type) {
                    'entry' => 'Ingreso', 'exit' => 'Salida', default => 'No aplica',
                })
                ->editColumn('status', fn ($record) => $record->status === 'present'
                    ? '<span class="badge bg-success-subtle text-success">Presente</span>'
                    : '<span class="badge bg-danger-subtle text-danger">Ausente</span>')
                ->addColumn('edit', fn ($record) => '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="'.$record->id.'" title="Editar asistencia" aria-label="Editar asistencia"><i class="bi bi-pencil-square"></i></button>')
                ->addColumn('delete', fn ($record) => '<form action="'.route('admin.attendances.destroy', $record->id).'" method="POST" class="m-0 frmEliminar">'.csrf_field().method_field('DELETE').'<button type="submit" class="btn btn-sm btn-danger" title="Eliminar asistencia" aria-label="Eliminar asistencia"><i class="bi bi-trash3-fill"></i></button></form>')
                ->rawColumns(['status', 'edit', 'delete'])->make(true);
        }

        return view('admin.employees.attendances.index', compact('startDate', 'endDate'));
    }

    public function create(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $attendance = new Attendance([
            'date' => $request->input('date') ?: now(Attendance::TIMEZONE)->format('Y-m-d'),
            'time' => now(Attendance::TIMEZONE)->format('H:i:s'),
            'status' => 'present',
        ]);

        return view('admin.employees.attendances.create', [
            'attendance' => $attendance, 'employees' => [],
        ]);
    }

    public function store(Request $request)
    {
        $this->attendances->save($this->validated($request));

        return response()->json(['message' => 'Asistencia registrada exitosamente.']);
    }

    public function edit(Attendance $attendance)
    {
        $employee = $attendance->employee()->first(['id', 'dni', 'first_name', 'last_name']);

        return view('admin.employees.attendances.edit', [
            'attendance' => $attendance,
            'employees' => $employee ? [$employee->id => $this->employeeLabel($employee)] : [],
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $this->attendances->save($this->validated($request), $attendance);

        return response()->json(['message' => 'Asistencia actualizada exitosamente.']);
    }

    public function destroy(Attendance $attendance)
    {
        $this->attendances->delete($attendance);

        return response()->json(['message' => 'Asistencia eliminada exitosamente.']);
    }

    public function preview(Request $request)
    {
        $data = $this->validated($request);
        $request->validate(['attendance_id' => 'nullable|integer|exists:attendances,id']);
        $type = $data['status'] === 'present'
            ? $this->attendances->nextType((int) $data['employee_id'], $data['date'], $data['time'], $request->integer('attendance_id') ?: null)
            : null;

        return response()->json(['type' => match ($type) {
            'entry' => 'Ingreso', 'exit' => 'Salida', default => 'No aplica (ausencia)',
        }]);
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

        $query = Employee::query();
        // Cada palabra puede coincidir con DNI, nombres o apellidos, en cualquier orden.
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

    private function employeeLabel(Employee $employee): string
    {
        return $employee->dni.' - '.$employee->last_name.', '.$employee->first_name;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'status' => ['required', Rule::in(['present', 'absent'])],
            'notes' => 'nullable|string|max:1000',
        ], [
            'employee_id.required' => 'El personal es obligatorio.',
            'employee_id.integer' => 'Seleccione un personal válido.',
            'employee_id.exists' => 'El personal seleccionado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date_format' => 'Ingrese una fecha válida.',
            'time.required' => 'La hora es obligatoria.',
            'time.regex' => 'Ingrese una hora válida.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'Seleccione un estado válido: presente o ausente.',
            'notes.string' => 'Las notas deben ser un texto.',
            'notes.max' => 'Las notas no deben superar los 1000 caracteres.',
        ]);
        if (strlen($data['time']) === 5) {
            $data['time'] .= ':00';
        }

        return $data;
    }
}
