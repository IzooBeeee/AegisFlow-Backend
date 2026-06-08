<?php

namespace App\Events;

use App\Models\FloodZone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FloodZoneUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FloodZone $floodZone
    ) {}

    public function broadcastOn(): array
    {
        return ['flood'];
    }

    public function broadcastAs(): string
    {
        return 'flood_zone.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->floodZone->id,
            'name' => $this->floodZone->name,
            'current_water_level_m' => $this->floodZone->current_water_level_m,
            'status' => $this->floodZone->status,
            'risk_level' => $this->floodZone->risk_level,
            'updated_at' => $this->floodZone->updated_at?->toIso8601String(),
        ];
    }
}
