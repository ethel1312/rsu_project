<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Programming\ChangeReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChangeReasonController extends Controller
{
    public function index()
    {
        return view('admin.programming.reasons.index', ['reasons' => ChangeReason::orderBy('name')->paginate(15)]);
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

        return redirect()->route('admin.programming.reasons.index')->with('success', 'Motivo registrado.');
    }

    public function update(Request $request, ChangeReason $reason)
    {
        $reason->update($this->data($request, $reason));

        return redirect()->route('admin.programming.reasons.index')->with('success', 'Motivo actualizado.');
    }

    public function destroy(ChangeReason $reason)
    {
        $reason->delete();

        return back()->with('success', 'Motivo eliminado del catálogo. Los cambios anteriores conservan su motivo.');
    }

    private function data(Request $request, ?ChangeReason $reason = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name')), 'description' => trim((string) $request->input('description'))]);

        return $request->validate(['name' => ['required', 'string', 'max:150', Rule::unique('change_reasons')->ignore($reason?->id)], 'description' => ['required', 'string', 'max:2000']]);
    }
}
