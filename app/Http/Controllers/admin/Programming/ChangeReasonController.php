<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Programming\ChangeReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ChangeReasonController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(ChangeReason::query()->select('change_reasons.*'))
                ->editColumn('created_at', fn ($reason) => $reason->created_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->editColumn('updated_at', fn ($reason) => $reason->updated_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->addColumn('actions', fn ($reason) => view('admin.programming.reasons.template.actions', compact('reason'))->render())
                ->escapeColumns(['name', 'description', 'created_at', 'updated_at'])
                ->rawColumns(['actions'])
                ->make(true);
        }

        return view('admin.programming.reasons.index');
    }

    public function create()
    {
        return view('admin.programming.reasons.form', ['reason' => new ChangeReason]);
    }

    public function edit(ChangeReason $reason)
    {
        return view('admin.programming.reasons.form', compact('reason'));
    }

    public function store(Request $request)
    {
        ChangeReason::create($this->data($request));

        return response()->json(['message' => 'Motivo registrado correctamente.']);
    }

    public function update(Request $request, ChangeReason $reason)
    {
        $reason->update($this->data($request, $reason));

        return response()->json(['message' => 'Motivo actualizado correctamente.']);
    }

    public function destroy(ChangeReason $reason)
    {
        $reason->delete();

        return response()->json(['message' => 'Motivo eliminado del catálogo. Los cambios anteriores conservan su motivo.']);
    }

    private function data(Request $request, ?ChangeReason $reason = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name')), 'description' => trim((string) $request->input('description'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('change_reasons')->ignore($reason?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Ingrese el nombre del motivo.',
            'name.max' => 'El nombre no debe superar los 150 caracteres.',
            'name.unique' => 'Ya existe un motivo con ese nombre.',
            'description.string' => 'La descripción debe ser un texto.',
            'description.max' => 'La descripción no debe superar los 2000 caracteres.',
        ]);
    }
}
