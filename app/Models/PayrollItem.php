<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollItem extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['payroll_run_id', 'employee_id', 'basic_salary', 'allowances', 'deductions', 'net_salary', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
