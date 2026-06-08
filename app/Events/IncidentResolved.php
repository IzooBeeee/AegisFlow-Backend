<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    public function broadcastAs(): string
    {
        return 'IncidentResolved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->incident->id,
            'status' => $this->incident->status,
            'resolved_at' => $this->incident->resolved_at?->toIso8601String(),
        ];
    }
}
