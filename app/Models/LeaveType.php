<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['name', 'days_per_year', 'is_paid', 'is_active'];

    protected $casts = ['is_paid' => 'boolean', 'is_active' => 'boolean'];
}
