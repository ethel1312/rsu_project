<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }
}
