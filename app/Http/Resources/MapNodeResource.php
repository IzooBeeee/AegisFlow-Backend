<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'type' => $this->type,
            'elevation_m' => $this->elevation_m,
            'is_safe' => $this->is_safe,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'flood_zone' => $this->whenLoaded('floodZone', fn () => [
                'id' => $this->floodZone->id,
                'name' => $this->floodZone->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
