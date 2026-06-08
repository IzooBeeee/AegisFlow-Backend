<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'predicted_value' => $this->predicted_value,
            'confidence' => $this->confidence,
            'probability' => $this->probability,
            'severity' => $this->severity,
            'risk_factors' => $this->risk_factors,
            'created_at' => $this->created_at,
        ];
    }
}
