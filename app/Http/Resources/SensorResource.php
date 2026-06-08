<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->translated('type'),
            'model' => $this->model,
            'status' => $this->status,
            'status_label' => $this->translated('status'),
            'location' => $this->location, // Dùng accessor location từ model
            'unit' => $this->unit,
            'last_value' => $this->last_value,
            'last_reading_at' => $this->last_reading_at?->toIso8601String(),
            'alert_threshold' => $this->alert_threshold,
            'danger_threshold' => $this->danger_threshold,
            'reading_interval_seconds' => $this->reading_interval_seconds,
            'metadata' => $this->metadata ?? [],
            'is_active' => $this->is_active,
            'flood_zone' => $this->whenLoaded('floodZone', function () {
                return [
                    'id' => $this->floodZone->id,
                    'name' => $this->floodZone->name,
                ];
            }),
            'district' => $this->whenLoaded('district', function () {
                return [
                    'id' => $this->district->id,
                    'name' => $this->district->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
