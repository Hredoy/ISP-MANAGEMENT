<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['name', 'starts_at', 'ends_at', 'grace_minutes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
