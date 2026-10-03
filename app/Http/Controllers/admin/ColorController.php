<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\ValidationException;

class ColorController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $colors = Color::select(['id', 'name', 'code', 'description', 'created_at', 'updated_at']);

            return DataTables::of($colors)
                ->addColumn('color_box', function ($color) {
                    return '<div style="width: 35px; height: 25px; background-color: ' . $color->code . '; border: 1px solid #ccc; border-radius: 4px; margin: 0 auto;"></div>';
                })
                // Fecha arriba (dd/mm/YYYY) y hora debajo (HH:mm)
                ->editColumn('created_at', function ($color) {
                    return $this->formatDate($color->created_at);
                })
                ->editColumn('updated_at', function ($color) {
                    return $this->formatDate($color->updated_at);
                })
                ->addColumn('edit', function ($color) {
                    // Botón azul igual que en Modelos
                    return '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="' . $color->id . '"><i class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn('delete', function ($color) {
                    // Formulario sin márgenes para evitar descuadres
                    return '<form action="' . route('admin.colors.destroy', $color->id) . '" method="POST" class="m-0 frmEliminar">' .
                        csrf_field() . method_field('DELETE') .
                        '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3-fill"></i></button></form>';
                })
                ->rawColumns(['color_box', 'created_at', 'updated_at', 'edit', 'delete'])
                ->make(true);
        }
        return view('admin.colors.index');
    }

    private function formatDate(\DateTimeInterface|string|null $value): string
    {
        if (!$value) {
            return '';
        }

        $d = Carbon::parse($value)->setTimezone('America/Lima');

        return '<div class="rsu-date">' . $d->format('d/m/Y') . '<small>' . $d->format('H:i') . '</small></div>';
    }

    // Quita espacios, agrega "#" si falta y lo guarda en MAYÚSCULAS.

    private function normalizeCode(Request $request): void
    {
        $code = strtoupper(trim((string) $request->input('code')));

        if ($code !== '' && !str_starts_with($code, '#')) {
            $code = '#' . $code;
        }

        $request->merge(['code' => $code]);
    }

    /**
     * Mensajes de validación en español.
     */
    private function messages(): array
    {
        return [
            'name.required' => 'El nombre del color es obligatorio.',
            'name.max'      => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'   => 'Ya existe un color con ese nombre.',
            'code.required' => 'El código del color es obligatorio.',
            'code.regex'    => 'El código debe tener el formato #RRGGBB (por ejemplo #FF0000).',
        ];
    }

    public function create()
    {
        $color = new Color();
        return view('admin.colors.create', compact('color'));
    }

    public function store(Request $request)
    {
        try {
            $this->normalizeCode($request);

            $request->validate(['name' => 'required|unique:colors', 'code' => 'required|regex:/^#[A-F0-9]{6}$/'], $this->messages());
            Color::create($request->all());
            return response()->json(['message' => 'Color registrado exitosamente.'], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function edit(string $id)
    {
        $color = Color::find($id);
        return view('admin.colors.edit', compact('color'));
    }

    public function update(Request $request, string $id)
    {
        try {
            $color = Color::find($id);
            $request->validate(['name' => 'required|unique:colors,name,' . $id, 'code' => 'required']);
            $color->update($request->all());
            return response()->json(['message' => 'Color actualizado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            Color::find($id)->delete();
            return response()->json(['message' => 'Color eliminado exitosamente.'], 200);
        } catch (QueryException $e) {
            // 23000 = restricción de llave foránea: el color está asignado a vehículos
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'No se puede eliminar: este color está asignado a uno o más vehículos.'
                ], 409);
            }
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error al eliminar: ' . $th->getMessage()], 500);
        }
    }
}
