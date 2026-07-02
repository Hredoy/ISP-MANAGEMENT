<?php

namespace App\Services\MikroTik\Resources;

use App\Services\MikroTik\ModeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Router row for the admin panel: the raw `Mikrotik` model plus its resolved effective mode, so
 * the frontend can show *why* a router is Demo/Real without re-implementing ModeResolver's logic.
 *
 * @mixin \App\Models\Mikrotik
 */
class MikrotikResource extends JsonResource
{
    public function __construct($resource, private readonly ModeResolver $modeResolver = new ModeResolver)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolution = $this->modeResolver->resolveWithSource($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'ssl_port' => $this->ssl_port,
            'connection_type' => $this->connection_type,
            'description' => $this->description,
            'router_version' => $this->router_version,
            'location' => $this->location,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'mode' => $this->mode,
            'effective_mode' => $resolution->mode,
            'mode_source' => $resolution->source,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'created_by' => $this->created_by,
        ];
    }
}
