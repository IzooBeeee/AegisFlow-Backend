<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Sensor;
use App\Support\DaNangLandMask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DanangSyncService
{
    const BASE_API_URL = 'https://muangap-api.danang.gov.vn';

    /**
     * Sync rainfall/water level stations
     */
    public function syncStations(): int
    {
        try {
            $response = Http::timeout(10)->get(self::BASE_API_URL.'/v1/stations?page=0&size=-1');

            if (! $response->successful()) {
                Log::error('DanangSyncService: Failed to fetch stations', ['status' => $response->status()]);

                return 0;
            }

            $data = $response->json('data') ?? [];
            $count = 0;

            foreach ($data as $item) {
                if (! isset($item['location']['coordinates'])) {
                    continue;
                }

                $lng = $item['location']['coordinates'][0];
                $lat = $item['location']['coordinates'][1];

                if (! DaNangLandMask::isLikelyLand((float) $lat, (float) $lng)) {
                    continue;
                }

                // Determine value (use depth_intensity for rain or depth for water)
                $value = $item['depth_intensity'] ?? $item['depth'] ?? 0;
                $externalId = $item['id'] ?? $item['code'] ?? null;

                if (! $externalId) {
                    continue;
                }

                // For PostGIS geometry creation
                $geom = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");

                $sensor = Sensor::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'name' => $item['name'] ?? 'Trạm đo Đà Nẵng',
                        'type' => 'rainfall', // Defaulting to rainfall as per endpoint
                        'status' => 'online',
                        'last_value' => $value,
                        'last_reading_at' => now(),
                        'metadata' => [
                            'source' => 'muangap.danang.gov.vn',
                            'original_data' => $item,
                        ],
                    ]
                );

                // Update geometry separately because Eloquent might not handle raw queries in updateOrCreate values well
                DB::table('sensors')->where('id', $sensor->id)->update(['geometry' => $geom]);
                $count++;
            }

            Log::info("DanangSyncService: Synced {$count} stations.");

            return $count;

        } catch (\Exception $e) {
            Log::error('DanangSyncService: Error syncing stations', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Sync flood reports from citizens
     */
    public function syncFloodReports(): int
    {
        try {
            // Fetch reports from the last 7 days
            $fromTime = now()->subDays(7)->timestamp;
            $toTime = now()->timestamp;

            $response = Http::timeout(10)->get(self::BASE_API_URL."/v1/flood/reports?from_time={$fromTime}&to_time={$toTime}");

            if (! $response->successful()) {
                Log::error('DanangSyncService: Failed to fetch flood reports', ['status' => $response->status()]);

                return 0;
            }

            $data = $response->json('data') ?? [];
            $count = 0;

            foreach ($data as $item) {
                if (! isset($item['_id']) || ! isset($item['location']['coordinates'])) {
                    continue;
                }

                $externalId = $item['_id'];
                $lng = $item['location']['coordinates'][0];
                $lat = $item['location']['coordinates'][1];

                if (! DaNangLandMask::isLikelyLand((float) $lat, (float) $lng)) {
                    continue;
                }
                $address = $item['location']['address'] ?? 'Đà Nẵng';
                $waterLevelCm = $item['water_level'] ?? 0;
                $waterLevelM = $waterLevelCm / 100;
                $description = $item['flood_description'] ?? 'Ngập lụt báo cáo từ hệ thống Thành phố';

                $photoUrls = [];
                if (isset($item['images']) && is_array($item['images'])) {
                    foreach ($item['images'] as $img) {
                        if (isset($img['img_url'])) {
                            $photoUrls[] = $img['img_url'];
                        }
                    }
                }

                $geom = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");

                // Prefix external ID to avoid collision with internal IDs
                $internalExtId = 'danang_report_'.$externalId;

                // Check if exists using metadata->external_id or create new
                $incident = Incident::whereJsonContains('metadata->external_id', $internalExtId)->first();

                if (! $incident) {
                    $incident = new Incident;
                    $incident->title = 'Báo cáo ngập: '.($item['location']['street_name'] ?? 'Khu vực Đà Nẵng');
                    $incident->type = 'flood';
                    $incident->severity = $waterLevelM > 0.5 ? 'high' : 'medium';
                    $incident->status = 'verified';
                    $incident->source = 'citizen';
                    $incident->address = $address;
                    $incident->water_level_m = $waterLevelM;
                    $incident->photo_urls = $photoUrls;
                    $incident->metadata = [
                        'external_id' => $internalExtId,
                        'source' => 'muangap.danang.gov.vn',
                        'description' => $description,
                        'original_data' => $item,
                    ];
                    $incident->save();

                    // Update geometry
                    DB::table('incidents')->where('id', $incident->id)->update(['geometry' => $geom]);
                    $count++;
                }
            }

            Log::info("DanangSyncService: Synced {$count} new flood reports.");

            return $count;

        } catch (\Exception $e) {
            Log::error('DanangSyncService: Error syncing flood reports', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
