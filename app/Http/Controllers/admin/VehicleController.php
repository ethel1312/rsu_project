<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Brand;
use App\Models\Brandmodel; 
use App\Models\Color;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Models\VehicleImage;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $vehicles = Vehicle::with(['type', 'brand', 'brandModel', 'color', 'profileImage'])->select('vehicles.*');

            return DataTables::of($vehicles)
                ->addColumn('image', function ($vehicle) {
                    if ($vehicle->profileImage) {
                        return '<img src="' . asset('storage/' . $vehicle->profileImage->image_path) . '" class="img-thumbnail" style="width: 70px; height: 50px; object-fit: cover;">';
                    }
                    return '<span class="text-muted small">Sin foto</span>';
                })
                ->addColumn('type_name', function ($vehicle) { return $vehicle->type->name ?? ''; })
                ->addColumn('brand_name', function ($vehicle) { return $vehicle->brand->name ?? ''; })
                ->addColumn('model_name', function ($vehicle) { return $vehicle->brandModel->name ?? ''; })
                ->addColumn('color_box', function ($vehicle) {
                    $colorCode = $vehicle->color->code ?? '#000000';
                    return '<div style="width: 25px; height: 25px; background-color: ' . $colorCode . '; border: 1px solid #ccc; border-radius: 4px; display: inline-block;"></div>';
                })
                ->addColumn('edit', function ($vehicle) {
                    return '<button type="button" class="btn btn-sm btn-primary btnEditar" data-id="' . $vehicle->id . '"><i class="bi bi-pencil-square"></i></button>';
                })
                ->addColumn('gallery', function ($vehicle) {
                    return '<button type="button" class="btn btn-sm btn-info text-white btnImages" data-id="' . $vehicle->id . '"><i class="bi bi-images"></i></button>';
                })
                ->addColumn('delete', function ($vehicle) {
                    return '<form action="' . route('admin.vehicles.destroy', $vehicle->id) . '" method="POST" class="m-0 frmEliminar">' .
                           csrf_field() . method_field('DELETE') .
                           '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash3-fill"></i></button></form>';
                })
                // Mapeo explícito para evitar que DataTables busque por nombres incorrectos
                ->filterColumn('type_name', function($query, $keyword) {
                    $query->whereHas('type', function($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('brand_name', function($query, $keyword) {
                    $query->whereHas('brand', function($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('model_name', function($query, $keyword) {
                    $query->whereHas('brandModel', function($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->rawColumns(['image', 'color_box', 'edit', 'gallery', 'delete'])
                ->make(true);
        }
        return view('admin.vehicles.index');
    }

    public function create()
    {
        $vehicle = new Vehicle();
        $types = VehicleType::pluck('name', 'id');
        $brands = Brand::pluck('name', 'id');
        $models = Brandmodel::pluck('name', 'id'); 
        $colors = Color::pluck('name', 'id');

        return view('admin.vehicles.create', compact('vehicle', 'types', 'brands', 'models', 'colors'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'code' => 'required|unique:vehicles,code',
                
                'plate' => ['required', 'unique:vehicles,plate', 'regex:/^([A-Z0-9]{6}|[A-Z0-9]{2}-[A-Z0-9]{4}|[A-Z0-9]{3}-[A-Z0-9]{3})$/i'],
                'year' => 'required|integer|min:1980|max:' . (date('Y') + 1), 
                'type_id' => 'required',
                'brand_id' => 'required',
                'model_id' => 'required',
                'color_id' => 'required',
            ]);

            Vehicle::create($request->all());
            return response()->json(['message' => 'Vehículo registrado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function edit(string $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $types = VehicleType::pluck('name', 'id');
        $brands = Brand::pluck('name', 'id');
        $models = Brandmodel::pluck('name', 'id'); 
        $colors = Color::pluck('name', 'id');

        return view('admin.vehicles.edit', compact('vehicle', 'types', 'brands', 'models', 'colors'));
    }

    public function update(Request $request, string $id)
    {
        try {
            $vehicle = Vehicle::find($id);
            $request->validate([
                'name' => 'required',
                'code' => 'required|unique:vehicles,code,' . $id, 
                'plate' => ['required', 'unique:vehicles,plate,' . $id, 'regex:/^([A-Z0-9]{6}|[A-Z0-9]{2}-[A-Z0-9]{4}|[A-Z0-9]{3}-[A-Z0-9]{3})$/i'],
                'year' => 'required|integer|min:1980|max:' . (date('Y') + 1),
                'type_id' => 'required',
                'brand_id' => 'required',
                'model_id' => 'required',
                'color_id' => 'required',
            ]);

            $vehicle->update($request->all());
            return response()->json(['message' => 'Vehículo actualizado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error: ' . $th->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            Vehicle::find($id)->delete();
            return response()->json(['message' => 'Vehículo eliminado exitosamente.'], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Error al eliminar: ' . $th->getMessage()], 500);
        }
    }
    /* |-------------------------------------------------------------------------- | GESTIÓN DE IMÁGENES |-------------------------------------------------------------------------- */
    
    public function getImages($id)
    {
        $vehicle = Vehicle::with('images')->findOrFail($id);
        // Retornamos una vista parcial que inyectaremos en el modal
        return view('admin.vehicles.template.images', compact('vehicle'));
    }

    public function uploadImage(Request $request, $id)
    {
        $request->validate(['file' => 'required|image|mimes:jpeg,png,jpg|max:2048']);
        $vehicle = Vehicle::findOrFail($id);

        if ($request->file('file')) {
            // Guardar físicamente la imagen en storage/app/public/vehicles
            $path = $request->file('file')->store('vehicles', 'public');
            
            // Si es la primera imagen, la hacemos perfil por defecto
            $isProfile = $vehicle->images()->count() === 0 ? true : false;

            $image = VehicleImage::create([
                'vehicle_id' => $vehicle->id,
                'image_path' => $path,
                'is_profile' => $isProfile
            ]);

            return response()->json(['success' => true, 'image' => $image]);
        }
        return response()->json(['error' => 'No se subió el archivo'], 400);
    }

    public function setProfileImage($image_id)
    {
        $image = VehicleImage::findOrFail($image_id);
        
        // Quitar el perfil a todas las imágenes de este vehículo
        VehicleImage::where('vehicle_id', $image->vehicle_id)->update(['is_profile' => false]);
        
        // Asignar perfil a la seleccionada
        $image->update(['is_profile' => true]);

        return response()->json(['message' => 'Imagen de perfil actualizada.']);
    }

    public function deleteImage($image_id)
    {
        $image = VehicleImage::findOrFail($image_id);
        
        // Borrar archivo físico
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        
        // Borrar registro de BD
        $image->delete();

        return response()->json(['message' => 'Imagen eliminada.']);
    }
}