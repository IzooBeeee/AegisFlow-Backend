<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Alert $alert
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AlertUpdated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'alert_number' => $this->alert->alert_number,
            'title' => $this->alert->title,
            'description' => $this->alert->description,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'status' => $this->alert->status,
            'source' => $this->alert->source,
            'effective_from' => $this->alert->effective_from?->toIso8601String(),
            'effective_until' => $this->alert->effective_until?->toIso8601String(),
            'updated_at' => $this->alert->updated_at?->toIso8601String(),
        ];
    }
}
