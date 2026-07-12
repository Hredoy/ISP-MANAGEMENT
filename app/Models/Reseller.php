<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'wallet_balance'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_reseller_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Reseller::class, 'parent_reseller_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ResellerCommission::class);
    }

    /** Walk up the ancestor chain and yield each reseller (excluding self). */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return $ancestors;
    }
}
