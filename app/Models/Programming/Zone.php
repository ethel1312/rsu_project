<?php

namespace App\Models\Programming;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $table = 'zones';

    protected $fillable = [
        'name',
        'department',
        'province',
        'district',
        'description',
        'average_waste_kg',
        'coordinates',
        'status',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'status' => 'boolean',
        'average_waste_kg' => 'decimal:2',
    ];
}