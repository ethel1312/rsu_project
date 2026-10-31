<?php

namespace App\Models\Programming;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeReason extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description'];
}
