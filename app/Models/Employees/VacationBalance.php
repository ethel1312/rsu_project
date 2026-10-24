<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'days_entitled',
        'days_taken',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function getDaysAvailableAttribute()
    {
        return $this->days_entitled - $this->days_taken;
    }
}
