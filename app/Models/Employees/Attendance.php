<?php

namespace App\Models\Employees;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const TIMEZONE = 'America/Lima';

    protected $fillable = ['employee_id', 'date', 'time', 'type', 'status', 'notes'];

    protected $casts = ['date' => 'date'];

    public function setDateAttribute($value): void
    {
        // Mantener una fecha sin hora tanto en MySQL como en SQLite.
        $this->attributes['date'] = Carbon::parse($value)->format('Y-m-d');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
