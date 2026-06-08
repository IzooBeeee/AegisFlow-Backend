<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'severity' => $this->severity,
            'status' => $this->status,
            'source' => $this->source,
            'address' => $this->address,
            'water_level_m' => $this->water_level_m,
            'rainfall_mm' => $this->rainfall_mm,
            'photo_urls' => $this->photo_urls ?? [],
            'geometry' => $this->when($this->geometry, function () {
                return [
                    'type' => 'Point',
                    'coordinates' => $this->getCoordinates(),
                ];
            }),
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'ward' => $this->whenLoaded('ward', fn () => [
                'id' => $this->ward->id,
                'name' => $this->ward->name,
            ]),
            'flood_zone' => $this->whenLoaded('floodZone', fn () => [
                'id' => $this->floodZone->id,
                'name' => $this->floodZone->name,
                'risk_level' => $this->floodZone->risk_level,
            ]),
            'reported_by' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'assigned_to' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'verified_by' => $this->whenLoaded('verifier', fn () => [
                'id' => $this->verifier->id,
                'name' => $this->verifier->name,
            ]),
            'verified_at' => $this->verified_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'metadata' => $this->metadata,
            'events_count' => $this->whenCounted('events'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Trích tọa độ từ PostGIS geometry
     */
    protected function getCoordinates(): ?array
    {
        if (!$this->geometry) {
            return null;
        }

        // Nếu đã dạng array
        if (is_array($this->geometry)) {
            return $this->geometry;
        }

        return null;
    }
}
