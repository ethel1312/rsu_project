<?php

namespace App\Models\Programming;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ScheduleChange extends Model
{
    public const TYPES = ['shift' => 'Cambio de turno', 'vehicle' => 'Cambio de vehículo', 'driver' => 'Cambio de conductor', 'helper' => 'Cambio de ayudante'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'before_data' => 'array', 'after_data' => 'array', 'reverted_at' => 'datetime'];
    }

    public function schedule()
    {
        return $this->belongsTo(ProgrammingSchedule::class, 'programming_schedule_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function reverter()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }
}
