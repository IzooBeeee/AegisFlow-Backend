<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShelterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'shelter_type' => $this->shelter_type,
            'capacity' => $this->capacity,
            'current_occupancy' => $this->current_occupancy,
            'available_capacity' => $this->capacity - $this->current_occupancy,
            'occupancy_rate' => $this->capacity > 0 ? round(($this->current_occupancy / $this->capacity) * 100, 1) : 0,
            'facilities' => $this->facilities ?? [],
            'status' => $this->status,
            'accessibility' => $this->accessibility,
            'is_flood_safe' => $this->is_flood_safe,
            'flood_depth_tolerance_m' => $this->flood_depth_tolerance_m,
            'contact_phone' => $this->contact_phone,
            'contact_name' => $this->contact_name,
            'opening_hours' => $this->opening_hours,
            'metadata' => $this->metadata,
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'ward' => $this->whenLoaded('ward', fn () => [
                'id' => $this->ward->id,
                'name' => $this->ward->name,
            ]),
            'location' => $this->location,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
