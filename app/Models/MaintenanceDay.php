<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDay extends Model
{
    protected $fillable = ['date', 'observation', 'image_path', 'is_completed'];
    protected $casts = ['is_completed' => 'boolean'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function hasEvidence(): bool
    {
        return $this->is_completed !== null || $this->observation !== null || $this->image_path !== null;
    }
}
