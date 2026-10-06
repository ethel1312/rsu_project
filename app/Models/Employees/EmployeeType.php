<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeType extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'description', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
