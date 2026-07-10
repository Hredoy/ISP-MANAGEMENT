<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['effective_status'];

    protected $casts = [
        'monthly_bill' => 'decimal:2',
        'credit_balance' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function sub_zone(): BelongsTo
    {
        return $this->belongsTo(SubZone::class);
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('full_name', 'like', "%{$term}%")
                ->orWhere('pppoe_username', 'like', "%{$term}%")
                ->orWhere('phone_number', 'like', "%{$term}%");
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['zone_id'] ?? null, fn (Builder $q, $zoneId) => $q->where('zone_id', $zoneId))
            ->when($filters['package_name'] ?? null, fn (Builder $q, $package) => $q->where('package_name', $package))
            ->when($filters['status'] ?? null, function (Builder $q, $status) {
                return match ($status) {
                    'Suspended' => $q->where('status', 'Suspended'),
                    'Expired' => $q->where('status', '!=', 'Suspended')->where('expiry_date', '<', now()->toDateString()),
                    'Active' => $q->where('status', 'Active')->where('expiry_date', '>=', now()->toDateString()),
                    default => $q,
                };
            });
    }

    /**
     * The status as displayed in the UI: an Active client whose expiry_date
     * has passed shows as Expired without needing a background job to flip
     * the stored column (Suspended is only ever set explicitly).
     */
    protected function effectiveStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->status === 'Suspended') {
                    return 'Suspended';
                }

                if ($this->expiry_date && \Carbon\Carbon::parse($this->expiry_date)->isPast()) {
                    return 'Expired';
                }

                return 'Active';
            },
        );
    }
}
