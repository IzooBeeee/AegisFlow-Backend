<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prediction_type' => $this->prediction_type,
            'model_version' => $this->model_version,
            'prediction_for' => $this->prediction_for,
            'horizon_minutes' => $this->horizon_minutes,
            'issued_at' => $this->issued_at,
            'predicted_value' => $this->predicted_value,
            'confidence' => $this->confidence,
            'probability' => $this->probability,
            'severity' => $this->severity,
            'input_data' => $this->input_data,
            'processing_time_ms' => $this->processing_time_ms,
            'status' => $this->status,
            'verified_at' => $this->verified_at,
            'model' => $this->whenLoaded('aiModel', fn () => [
                'id' => $this->aiModel->id,
                'name' => $this->aiModel->name,
                'model_type' => $this->aiModel->model_type,
            ]),
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'flood_zone' => $this->whenLoaded('floodZone', fn () => [
                'id' => $this->floodZone->id,
                'name' => $this->floodZone->name,
            ]),
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
            ]),
            'details' => PredictionDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at,
        ];
    }
}
