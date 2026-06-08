<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloodZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'risk_level' => $this->risk_level,
            'risk_level_label' => $this->translated('risk_level'),
            'status' => $this->status,
            'status_label' => $this->translated('status'),
            'current_water_level_m' => $this->current_water_level_m,
            'alert_threshold_m' => $this->alert_threshold_m,
            'danger_threshold_m' => $this->danger_threshold_m,
            'area_km2' => $this->area_km2,
            'population_affected' => $this->population_affected,
            'color' => $this->color,
            'opacity' => $this->opacity,
            'centroid' => $this->centroid_array, // Dùng accessor centroid_array từ model
            'geometry' => $this->when($request->has('with_geometry'), function () {
                return $this->toGeoJson()['geometry'];
            }),
            'is_active' => $this->is_active,
            'district' => $this->whenLoaded('district', function () {
                return [
                    'id' => $this->district->id,
                    'name' => $this->district->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
