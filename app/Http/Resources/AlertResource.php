<?php

namespace App\Http\Resources;

use App\Models\District;
use App\Models\FloodZone;
use App\Support\DaNangLandMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $affectedFloodZoneIds = collect($this->affected_flood_zones ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        $affectedDistrictIds = collect($this->affected_districts ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        $affectedFloodZones = $affectedFloodZoneIds->isNotEmpty()
            ? FloodZone::with('district')
                ->whereIn('id', $affectedFloodZoneIds)
                ->get()
                ->map(fn (FloodZone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'district' => $zone->district ? [
                        'id' => $zone->district->id,
                        'name' => $zone->district->name,
                    ] : null,
                    'centroid' => $zone->centroid_array,
                ])
                ->values()
            : collect();
        $affectedDistricts = $affectedDistrictIds->isNotEmpty()
            ? District::whereIn('id', $affectedDistrictIds)
                ->get(['id', 'name'])
                ->map(fn (District $district) => [
                    'id' => $district->id,
                    'name' => $district->name,
                ])
                ->values()
            : collect();
        $area = $affectedFloodZones->pluck('name')->filter()->join(', ');
        if (! $area) {
            $area = $affectedDistricts->pluck('name')->filter()->join(', ');
        }

        // Extract coordinates from PostGIS geometry
        $geometry = null;
        if (DB::connection()->getDriverName() === 'pgsql') {
            $result = DB::selectOne(
                'SELECT ST_AsGeoJSON(geometry) as geojson FROM alerts WHERE id = ?',
                [$this->id]
            );
            if ($result?->geojson) {
                $geometry = json_decode($result->geojson, true);
                if (! DaNangLandMask::featureIsLikelyLand([
                    'type' => 'Feature',
                    'geometry' => $geometry,
                ])) {
                    $geometry = null;
                }
            }
        }

        return [
            'id' => $this->id,
            'alert_number' => $this->alert_number,
            'title' => $this->title,
            'description' => $this->description,
            'alert_type' => $this->alert_type,
            'alert_type_label' => $this->translated('alert_type'),
            'severity' => $this->severity,
            'severity_label' => $this->translated('severity'),
            'status' => $this->status,
            'status_label' => $this->translated('status'),
            'affected_districts' => $this->affected_districts ?? [],
            'affected_district_details' => $affectedDistricts,
            'affected_flood_zones' => $this->affected_flood_zones ?? [],
            'affected_flood_zone_details' => $affectedFloodZones,
            'area' => $area ?: null,
            'radius_km' => $this->radius_km,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_until' => $this->effective_until?->toIso8601String(),
            'geometry' => $geometry,
            'photo_urls' => $this->relatedIncident?->photo_urls ?? [],
            'address' => $this->relatedIncident?->address,
            'related_incident' => $this->relatedIncident ? [
                'id' => $this->relatedIncident->id,
                'title' => $this->relatedIncident->title,
                'address' => $this->relatedIncident->address,
                'severity' => $this->relatedIncident->severity,
                'status' => $this->relatedIncident->status,
                'location' => $this->relatedIncident->location,
            ] : null,
            'issuer' => $this->issuer ? [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
