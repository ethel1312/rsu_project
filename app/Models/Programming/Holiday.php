<?php

namespace App\Models\Programming;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    protected $fillable = ['date', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_active' => 'boolean'];
    }

    protected function date(): Attribute
    {
        // Keep DATE values identical in MySQL and SQLite, including unique lookups.
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->toDateString());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
