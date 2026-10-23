<?php

namespace App\Models\Programming;

use App\Models\Employees\Employee;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PersonnelGroup extends Model
{
    public const WEEKDAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $fillable = ['name', 'shift_id', 'zone_id', 'vehicle_id', 'driver_id', 'days_of_week', 'status'];

    protected $casts = ['days_of_week' => 'array', 'status' => 'boolean'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function helpers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'personnel_group_helpers', 'personnel_group_id', 'employee_id');
    }

    public function getDaysLabelAttribute(): string
    {
        return collect($this->days_of_week)->sort()->map(fn ($day) => self::WEEKDAYS[$day] ?? '')->implode(', ');
    }
}
