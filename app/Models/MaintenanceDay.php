<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MaintenanceDay extends Model
{
    public const STATUSES = [
        'pending' => 'Pendiente',
        'done' => 'Realizado',
        'not_done' => 'No realizado',
    ];

    protected $fillable = ['date', 'observation', 'image_path', 'status'];

    protected function date(): Attribute
    {
        return Attribute::get(fn (string $date) => CarbonImmutable::parse($date));
    }
}
