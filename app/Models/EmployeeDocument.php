<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['employee_id', 'title', 'type', 'path'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
