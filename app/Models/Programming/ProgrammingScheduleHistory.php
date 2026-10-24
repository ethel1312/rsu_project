<?php

namespace App\Models\Programming;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammingScheduleHistory extends Model
{
    protected $fillable = [
        'programming_schedule_id', 'schedule_label', 'changed_by', 'action',
        'before_data', 'after_data', 'notes',
    ];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProgrammingSchedule::class, 'programming_schedule_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
