<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['employee_id', 'attendance_date', 'check_in_at', 'check_out_at', 'status', 'notes'];

    protected $casts = ['attendance_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
