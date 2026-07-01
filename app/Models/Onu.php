<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Onu extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = [
        'olt_id',
        'device_id',
        'client_id',
        'serial_number',
        'mac_address',
        'pon_port',
        'onu_id',
        'status',
        'rx_dbm',
        'tx_dbm',
        'signal_color',
        'signal_label',
        'signal_metrics',
        'last_seen_at',
    ];

    protected $casts = [
        'rx_dbm' => 'decimal:2',
        'tx_dbm' => 'decimal:2',
        'signal_metrics' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
