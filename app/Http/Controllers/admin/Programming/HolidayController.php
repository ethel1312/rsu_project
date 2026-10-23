<?php

namespace App\Http\Controllers\admin\Programming;

use App\Exceptions\HolidayImportException;
use App\Http\Controllers\Controller;
use App\Models\Programming\Holiday;
use App\Services\Programming\HolidayImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
                'status' => ['nullable', Rule::in(['0', '1'])],
            ], [
                'date_from.date_format' => 'Ingrese una fecha de inicio válida.',
                'date_to.date_format' => 'Ingrese una fecha de fin válida.',
                'date_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
                'status.in' => 'Seleccione un estado válido.',
            ]);

            $holidays = Holiday::query()->select('holidays.*')
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date));
            if (isset($filters['status']) && $filters['status'] !== '') {
                $holidays->where('is_active', (bool) $filters['status']);
            }

            return DataTables::of($holidays)
                ->editColumn('date', fn ($holiday) => $holiday->date->format('d/m/Y'))
                ->addColumn('day_name', fn ($holiday) => ucfirst($holiday->date->locale('es')->isoFormat('dddd')))
                ->editColumn('created_at', fn ($holiday) => $holiday->created_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->addColumn('actions', fn ($holiday) => view('admin.programming.holidays.template.actions', compact('holiday'))->render())
                // Description is rendered with DataTables' text renderer in the browser.
                ->escapeColumns(['date', 'day_name', 'created_at'])
                ->rawColumns(['actions'])
                ->with('stats', $this->stats())
                ->make(true);
        }

        return view('admin.programming.holidays.index', ['stats' => $this->stats()]);
    }

    public function create()
    {
        return view('admin.programming.holidays.create');
    }

    public function store(Request $request)
    {
        Holiday::create($this->validated($request));

        return response()->json(['message' => 'Feriado registrado exitosamente.']);
    }

    public function edit(Holiday $holiday)
    {
        return view('admin.programming.holidays.edit', compact('holiday'));
    }

    public function update(Request $request, Holiday $holiday)
    {
        $holiday->update($this->validated($request, $holiday));

        return response()->json(['message' => 'Feriado actualizado exitosamente.']);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json(['message' => 'Feriado eliminado correctamente.']);
    }

    public function toggleStatus(Request $request, Holiday $holiday)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']], [
            'is_active.required' => 'Seleccione el estado del feriado.',
            'is_active.boolean' => 'Seleccione un estado válido.',
        ]);
        $holiday->update($data);

        return response()->json(['message' => $holiday->is_active ? 'Feriado activado correctamente.' : 'Feriado desactivado correctamente.']);
    }

    public function import(Request $request, HolidayImportService $importer)
    {
        $currentYear = now('America/Lima')->year;
        $data = $request->validate(['year' => ['required', 'integer', 'between:'.$currentYear.','.($currentYear + 5)]], [
            'year.required' => 'Seleccione el año de los feriados.',
            'year.integer' => 'Ingrese un año válido.',
            'year.between' => 'El servicio permite cargar feriados desde '.$currentYear.' hasta '.($currentYear + 5).'. Para otros años, regístrelos manualmente.',
        ]);

        try {
            $result = $importer->import((int) $data['year']);
        } catch (HolidayImportException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'message' => 'Se agregaron '.$result['created'].' feriados de Perú para '.$data['year'].'. Ya existían '.$result['skipped'].' y se conservaron su descripción y estado.',
            ...$result,
        ]);
    }

    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->ignore($holiday)],
            'description' => ['required', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ], [
            'date.required' => 'La fecha del feriado es obligatoria.',
            'date.date_format' => 'Ingrese una fecha válida para el feriado.',
            'date.unique' => 'Ya existe un feriado registrado para esa fecha. Puede editarlo desde el listado.',
            'description.required' => 'La descripción del feriado es obligatoria.',
            'description.string' => 'Ingrese una descripción válida.',
            'description.max' => 'La descripción no debe superar los 1000 caracteres.',
            'is_active.required' => 'Seleccione el estado del feriado.',
            'is_active.boolean' => 'Seleccione un estado válido.',
        ]);
    }

    private function stats(): array
    {
        return [
            'total' => Holiday::count(),
            'active' => Holiday::active()->count(),
            'upcoming' => Holiday::active()->whereDate('date', '>=', now('America/Lima')->toDateString())->count(),
            'year' => now('America/Lima')->year,
        ];
    }
}
