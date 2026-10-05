<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $brands = Brand::all();

        if ($request->ajax()) {

            return DataTables::of($brands)
                ->addColumn("logo", function ($brand) {
                    return '<img src="' . ($brand->logo == '' ? asset('/storage/images/no_logo.png') : asset($brand->logo)) . '"
                                    style ="width:60px; height:50">';
                })
                // Fecha arriba (dd/mm/YYYY) y hora debajo (HH:mm)
                ->editColumn('created_at', function ($color) {
                    return $this->formatDate($color->created_at);
                })
                ->editColumn('updated_at', function ($color) {
                    return $this->formatDate($color->updated_at);
                })
                ->addColumn("edit", function ($brand) {
                    return '<button class="btn btn-sm btn-primary btnEditar" data-id=' . $brand->id . '><i
                                        class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn("delete", function ($brand) {
                    return ' <form action="' . route('admin.brands.destroy', $brand->id) . '" method="POST"
                                    class="frmEliminar">' . csrf_field() . method_field('DELETE') . '
                                    <button type="submit" class="btn btn-sm btn-danger"><i
                                            class="bi bi-trash3-fill"></i></button>
                                </form>';
                })
                ->rawColumns(['logo', 'created_at', 'updated_at', 'edit', 'delete'])
                ->make(true);
        } else {
            return view('admin.brands.index', compact('brands'));
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

    private function messages(): array
    {
        return [
            'name.required' => 'El nombre de la marca es obligatorio.',
            'name.max'      => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'   => 'Ya existe una marca con ese nombre.',
            'logo.image'    => 'El logo debe ser una imagen.',
            'logo.mimes'    => 'El logo debe ser JPEG, PNG, JPG, GIF o WEBP.',
            'logo.max'      => 'El logo no puede pesar más de 2 MB.',
        ];
    }

    public function create()
    {
        $brand = new Brand(); 
        return view('admin.brands.create', compact('brand')); 
    }
    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|unique:brands',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ], $this->messages());

            $logo = null;
            if($request->hasFile('logo')){
                $image = $request->file('logo')->store('brand_logo','public');
                $logo = Storage::url($image);
            }

            Brand::create([
                'name' => $request->name,
                'logo' => $logo,
                'description' => $request->description
            ]);

            // Respuesta AJAX
            return response()->json(['message' => 'Marca registrada exitosamente.'], 200);

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
        $brand = Brand::find($id);
        return view('admin.brands.edit', compact('brand'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $brand = Brand::find($id);

            $request->validate([
                'name' => 'required|unique:brands,name,' . $id,
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ], $this->messages());

            if($request->hasFile('logo')){
                $image = $request->file('logo')->store('brand_logo','public');
                $logo = Storage::url($image);

                $brand->update([
                    'name' => $request->name,
                    'logo' => $logo,
                    'description' => $request->description
                ]);
            } else {
                $brand->update($request->except(['logo']));
            }

            // Respuesta AJAX
            return response()->json(['message' => 'Marca actualizada exitosamente.'], 200);

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
            $brand = Brand::find($id);
            $brand->delete();
            
            // Respuesta AJAX exitosa
            return response()->json(['message' => 'Marca eliminada exitosamente.'], 200);
        } catch (QueryException $e) {
            // 23000 = restricción de llave foránea: la marca está en uso
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'No se puede eliminar: esta marca está asignada a uno o más modelos o vehículos.'
                ], 409);
            }
            return response()->json(['error' => 'Error de eliminación: ' . $e->getMessage()], 500);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error de eliminación: ' . $th->getMessage()], 500);
        }
    }
}
