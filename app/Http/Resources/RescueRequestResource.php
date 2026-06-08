<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescueRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'caller_name' => $this->caller_name,
            'caller_phone' => $this->caller_phone,
            'address' => $this->address,
            'urgency' => $this->urgency,
            'category' => $this->category,
            'people_count' => $this->people_count,
            'vulnerable_groups' => $this->vulnerable_groups ?? [],
            'description' => $this->description,
            'photo_urls' => $this->photo_urls ?? [],
            'water_level_m' => $this->water_level_m,
            'accessibility_notes' => $this->accessibility_notes,
            'location' => $this->location,
            'status' => $this->status,
            'priority_score' => $this->priority_score,
            'eta_minutes' => $this->eta_minutes,
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'cancellation_reason' => $this->cancellation_reason,
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'ward' => $this->whenLoaded('ward', fn () => [
                'id' => $this->ward->id,
                'name' => $this->ward->name,
            ]),
            'incident' => $this->whenLoaded('incident', fn () => [
                'id' => $this->incident->id,
                'title' => $this->incident->title,
                'severity' => $this->incident->severity,
            ]),
            'assigned_team' => $this->whenLoaded('assignedTeam', fn () => new RescueTeamResource($this->assignedTeam)),
            'reported_by' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'assigned_at' => $this->assigned_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
