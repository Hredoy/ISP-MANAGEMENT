<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['period', 'status', 'gross_total', 'deduction_total', 'net_total'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
