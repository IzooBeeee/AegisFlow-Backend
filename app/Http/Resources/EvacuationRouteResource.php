<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvacuationRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'distance_m' => $this->distance_m,
            'estimated_time_seconds' => $this->estimated_time_seconds,
            'estimated_time_display' => $this->formatTime($this->estimated_time_seconds),
            'is_safe' => $this->is_safe,
            'safety_rating' => $this->safety_rating,
            'risk_factors' => $this->risk_factors,
            'is_primary' => $this->is_primary,
            'max_capacity' => $this->max_capacity,
            'current_usage' => $this->current_usage,
            'status' => $this->status,
            'color' => $this->color,
            'polyline' => $this->polyline,
            'start_node' => $this->whenLoaded('startNode', fn () => [
                'id' => $this->startNode->id,
                'name' => $this->startNode->name,
                'type' => $this->startNode->type,
            ]),
            'end_node' => $this->whenLoaded('endNode', fn () => [
                'id' => $this->endNode->id,
                'name' => $this->endNode->name,
                'type' => $this->endNode->type,
            ]),
            'flood_zone' => $this->whenLoaded('floodZone', fn () => [
                'id' => $this->floodZone->id,
                'name' => $this->floodZone->name,
            ]),
            'segments_count' => $this->whenCounted('segments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Format thời gian hiển thị
     */
    protected function formatTime(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return "{$minutes} phút";
        }
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        return "{$hours}h{$remaining}p";
    }
}
