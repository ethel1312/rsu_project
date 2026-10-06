<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'dni',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'email',
        'status',
        'password',
        'address',
        'image_path',
        'employee_type_id'
    ];

    public function employeeType()
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type_id');
    }

    public function contracts()
    {
        return $this->hasMany(\App\Models\Employees\Contract::class);
    }

    public function getFullNameAttribute()
    {
        // Revisa todas las combinaciones usuales del proyecto
        $nombres = $this->first_name ?? $this->names ?? $this->name ?? '';
        $apellidos = $this->last_name ?? $this->last_names ?? $this->surnames ?? '';

        return trim("{$nombres} {$apellidos}");
    }
}
