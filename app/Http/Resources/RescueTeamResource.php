<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescueTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'team_type' => $this->team_type,
            'specializations' => $this->specializations ?? [],
            'vehicle_count' => $this->vehicle_count,
            'personnel_count' => $this->personnel_count,
            'equipment' => $this->equipment,
            'phone' => $this->phone,
            'status' => $this->status,
            'current_latitude' => $this->current_latitude,
            'current_longitude' => $this->current_longitude,
            'last_location_update' => $this->last_location_update,
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'heading_to_incident' => $this->whenLoaded('headingToIncident', fn () => [
                'id' => $this->headingToIncident->id,
                'title' => $this->headingToIncident->title,
            ]),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
