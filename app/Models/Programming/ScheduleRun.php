<?php

namespace App\Models\Programming;

use Illuminate\Database\Eloquent\Model;

class ScheduleRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'route_points' => 'array'];
    }
}
