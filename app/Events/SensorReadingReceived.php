<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class SensorReadingReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public array $data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    public function broadcastAs(): string
    {
        return 'SensorReadingReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'sensor_id' => $this->data['sensor_id'] ?? null,
            'type' => $this->data['type'] ?? 'unknown',
            'water_level' => $this->data['water_level'] ?? null,
            'rainfall' => $this->data['rainfall'] ?? null,
            'temperature' => $this->data['temperature'] ?? null,
            'humidity' => $this->data['humidity'] ?? null,
            'latitude' => $this->data['latitude'] ?? null,
            'longitude' => $this->data['longitude'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
