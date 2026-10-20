<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Programming\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(Shift::query()->select('shifts.*'))
                ->editColumn('start_time', fn ($shift) => substr($shift->start_time, 0, 5))
                ->editColumn('end_time', fn ($shift) => substr($shift->end_time, 0, 5))
                ->editColumn('created_at', fn ($shift) => $shift->created_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->addColumn('actions', fn ($shift) => view('admin.programming.shifts.template.actions', compact('shift'))->render())
                ->rawColumns(['actions'])
                ->make(true);
        }

        return view('admin.programming.shifts.index');
    }

    public function create()
    {
        return view('admin.programming.shifts.create');
    }

    public function store(Request $request)
    {
        Shift::create($this->validated($request));

        return response()->json(['message' => 'Turno registrado exitosamente.']);
    }

    public function edit(Shift $shift)
    {
        return view('admin.programming.shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validated($request, $shift));

        return response()->json(['message' => 'Turno actualizado exitosamente.']);
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return response()->json(['message' => 'Turno eliminado correctamente.']);
    }

    private function validated(Request $request, ?Shift $shift = null): array
    {
        // Una hora de término menor que la de inicio representa el día siguiente.
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('shifts', 'name')->ignore($shift)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'El nombre del turno es obligatorio.',
            'name.string' => 'Ingrese un nombre válido.',
            'name.max' => 'El nombre no debe superar los 150 caracteres.',
            'name.unique' => 'Ya existe un turno con ese nombre.',
            'start_time.required' => 'La hora de inicio es obligatoria.',
            'start_time.date_format' => 'Ingrese una hora de inicio válida (HH:mm).',
            'end_time.required' => 'La hora de término es obligatoria.',
            'end_time.date_format' => 'Ingrese una hora de término válida (HH:mm).',
            'end_time.different' => 'La hora de término debe ser distinta de la hora de inicio.',
            'description.string' => 'La descripción debe ser un texto.',
            'description.max' => 'La descripción no debe superar los 1000 caracteres.',
        ]);
    }
}
