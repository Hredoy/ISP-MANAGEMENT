<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBlog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
