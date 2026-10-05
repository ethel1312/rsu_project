<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Brandmodel;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class BrandmdelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $models = Brandmodel::select(
            'brandmodels.id', // <--- LÍNEA AGREGADA: Necesaria para los botones
            'brandmodels.name as model',
            'brandmodels.code',
            'b.name as brand',
            'brandmodels.description',
            'brandmodels.created_at',
            'brandmodels.updated_at'
        )
        ->join('brands as b','b.id','=','brandmodels.brand_id')
        ->get();

        if ($request->ajax()) {
            return DataTables::of($models)
                ->editColumn('created_at', function ($color) {
                    return $this->formatDate($color->created_at);
                })
                ->editColumn('updated_at', function ($color) {
                    return $this->formatDate($color->updated_at);
                })
                ->addColumn("edit", function ($model) {
                    return '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="' . $model->id . '"><i class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn("delete", function ($model) {
                    return '<form action="' . route('admin.models.destroy', $model->id) . '" method="POST" class="frmEliminar">' 
                                . csrf_field() 
                                . method_field('DELETE') . 
                                '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3-fill"></i></button>
                            </form>';
                })
                ->rawColumns(['edit', 'created_at', 'updated_at', 'delete'])
                ->make(true);
        } else {
            return view('admin.models.index'); // No es necesario enviar compact('models') si usas AJAX
        }
    }

    private function formatDate(\DateTimeInterface|string|null $value): string
    {
        if (!$value) {
            return '';
        }

        $d = Carbon::parse($value)->setTimezone('America/Lima');

        return '<div class="rsu-date">' . $d->format('d/m/Y') . '<small>' . $d->format('H:i') . '</small></div>';
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $brands = Brand::pluck('name', 'id');
        $model = new Brandmodel();
        return view('admin.models.create', compact('brands', 'model'));
    }

    private function messages(): array
    {
        return [
            'name.required'     => 'El nombre del modelo es obligatorio.',
            'name.max'          => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'       => 'Ya existe un modelo con ese nombre.',
            'code.required'     => 'El código del modelo es obligatorio.',
            'code.max'          => 'El código no puede superar los 50 caracteres.',
            'code.unique'       => 'Ya existe un modelo con ese código.',
            'brand_id.required' => 'Debe seleccionar una marca.',
            'brand_id.exists'   => 'La marca seleccionada no existe.',
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|unique:brandmodels', // Corregido el nombre de la tabla
                'brand_id' => 'required'
            ], $this->messages());
            
            Brandmodel::create($request->all());
            
            return response()->json(['message' => 'Modelo registrado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error de registro: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $model = Brandmodel::find($id); // Renombrado a $model
        $brands = Brand::pluck('name', 'id'); // Necesitamos las marcas para el select
        return view('admin.models.edit', compact('model', 'brands')); // Apuntando a la vista correcta
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $model = Brandmodel::find($id);

            $request->validate([
                'name' => 'required|unique:brandmodels,name,' . $id,
                'brand_id' => 'required'
            ],  $this->messages());
            
            // Eliminamos la lógica de imagen porque los modelos no tienen logo
            $model->update($request->all());

            return response()->json(['message' => 'Modelo actualizado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error de actualización: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $model = Brandmodel::find($id);
            $model->delete();
            return response()->json(['message' => 'Modelo eliminado exitosamente.'], 200);
        } catch (QueryException $e) {
            // 23000 = restricción de llave foránea: el modelo está en uso
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'No se puede eliminar: este modelo está asignado a uno o más vehículos.'
                ], 409);
            }
            return response()->json(['error' => 'Error de eliminación: ' . $e->getMessage()], 500);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error de eliminación: ' . $th->getMessage()], 500);
        }
    }
}
