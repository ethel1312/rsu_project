<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

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
                ->rawColumns(['color_box', 'edit', 'delete'])
                ->make(true);
        }
        return view('admin.colors.index');
    }

    public function create()
    {
        $color = new Color();
        return view('admin.colors.create', compact('color'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate(['name' => 'required|unique:colors', 'code' => 'required']);
            Color::create($request->all());
            return response()->json(['message' => 'Color registrado exitosamente.'], 200);
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
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error al eliminar: ' . $th->getMessage()], 500);
        }
    }
}