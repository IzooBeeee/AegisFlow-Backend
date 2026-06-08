<?php

namespace App\Events;

use App\Models\Prediction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Prediction $prediction
    ) {}

    public function broadcastOn(): array
    {
        return ['flood'];
    }

    public function broadcastAs(): string
    {
        return 'PredictionReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->prediction->id,
            'prediction_type' => $this->prediction->prediction_type,
            'probability' => $this->prediction->probability,
            'severity' => $this->prediction->severity,
            'confidence' => $this->prediction->confidence,
            'predicted_value' => $this->prediction->predicted_value,
            'flood_zone_id' => $this->prediction->flood_zone_id,
            'horizon_minutes' => $this->prediction->horizon_minutes,
            'prediction_for' => $this->prediction->prediction_for?->toIso8601String(),
            'issued_at' => $this->prediction->issued_at?->toIso8601String(),
            'created_at' => $this->prediction->created_at?->toIso8601String(),
            'input_data' => $this->prediction->input_data,
            'district' => $this->prediction->relationLoaded('district') ? [
                'id' => $this->prediction->district->id,
                'name' => $this->prediction->district->name,
            ] : null,
            'flood_zone' => $this->prediction->relationLoaded('floodZone') ? [
                'id' => $this->prediction->floodZone->id,
                'name' => $this->prediction->floodZone->name,
            ] : null,
        ];
    }
}
