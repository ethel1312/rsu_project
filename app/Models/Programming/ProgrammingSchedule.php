<?php

namespace App\Models\Programming;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgrammingSchedule extends Model
{
    protected $fillable = [
        'batch_uuid', 'personnel_group_id', 'group_name', 'shift_id', 'shift_name',
        'shift_start_time', 'shift_end_time', 'zone_id', 'zone_name', 'vehicle_id',
        'vehicle_label', 'driver_id', 'driver_name', 'helper_ids', 'helper_names',
        'days_of_week', 'start_date', 'end_date', 'status', 'finished_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'helper_ids' => 'array',
            'helper_names' => 'array',
            'days_of_week' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'finished_at' => 'datetime',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProgrammingScheduleHistory::class);
    }
}
