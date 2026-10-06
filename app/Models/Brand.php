<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name', 'description', 'logo'];

    public function models()
    {
        return $this->hasMany(Brandmodel::class, 'brand_id');
    }
}