<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDay extends Model
{
    protected $fillable = ['maintenance_schedule_id', 'maintenance_date', 'observation', 'image_path', 'status'];

    protected function casts(): array
    {
        return ['maintenance_date' => 'date'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }
}
