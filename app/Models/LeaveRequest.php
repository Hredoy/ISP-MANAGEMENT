<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'status', 'reason', 'approved_by', 'approved_at'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
