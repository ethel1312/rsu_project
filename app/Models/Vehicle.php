<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 
        'plate', 
        'year', 
        'name', 
        'type_id', 
        'brand_id', 
        'model_id', 
        'color_id', 
        'load_capacity', 
        'compact_capacity', 
        'fuel_capacity', 
        'occupant_capacity', 
        'description'
    ];

    public function images()
    {
        return $this->hasMany(VehicleImage::class);
    }

    public function profileImage()
    {
        return $this->hasOne(VehicleImage::class)->where('is_profile', true);
    }

    // Relaciones con los catálogos
    public function type() { return $this->belongsTo(VehicleType::class, 'vehicle_type_id'); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function brandModel() { return $this->belongsTo(Brandmodel::class, 'model_id'); } 
    public function color() { return $this->belongsTo(Color::class); }
}
