<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function startDate(): Attribute
    {
        return Attribute::get(fn (string $date) => CarbonImmutable::parse($date));
    }

    protected function endDate(): Attribute
    {
        return Attribute::get(fn (string $date) => CarbonImmutable::parse($date));
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }
}
