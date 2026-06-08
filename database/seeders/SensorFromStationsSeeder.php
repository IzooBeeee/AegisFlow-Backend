<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Migrate dữ liệu từ sensor_stations (crawled) → sensors (app table)
 * 175 trạm thực tế Đà Nẵng với tọa độ PostGIS
 */
class SensorFromStationsSeeder extends Seeder
{
    // Map station_type → sensors.type (SensorTypeEnum)
    private array $typeMap = [
        'water_level'          => 'water_level',
        'reservoir_waterlevel' => 'water_level',
        'rain_station'         => 'rainfall',
        'flood_3m'             => 'combined',
        'flood_1m5'            => 'combined',
        'unknown'              => 'water_level',
    ];

    // Map station_type → unit
    private array $unitMap = [
        'water_level'          => 'cm',
        'reservoir_waterlevel' => 'm',
        'rain_station'         => 'mm',
        'flood_3m'             => 'cm',
        'flood_1m5'            => 'cm',
        'unknown'              => 'cm',
    ];

    // Map station_type → alert/danger thresholds (cm hoặc mm)
    private array $thresholdMap = [
        'water_level'          => ['alert' => 50.0,  'danger' => 100.0],
        'reservoir_waterlevel' => ['alert' => 2.0,   'danger' => 4.0],
        'rain_station'         => ['alert' => 50.0,  'danger' => 100.0],
        'flood_3m'             => ['alert' => 100.0, 'danger' => 200.0],
        'flood_1m5'            => ['alert' => 75.0,  'danger' => 150.0],
        'unknown'              => ['alert' => 50.0,  'danger' => 100.0],
    ];

    public function run(): void
    {
        $stations = DB::table('sensor_stations')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $inserted = 0;
        $skipped  = 0;

        foreach ($stations as $station) {
            // Skip nếu đã tồn tại
            if (DB::table('sensors')->where('external_id', $station->external_id)->exists()) {
                $skipped++;
                continue;
            }

            $stationType = $station->station_type ?? 'unknown';
            $sensorType  = $this->typeMap[$stationType]  ?? 'water_level';
            $unit        = $this->unitMap[$stationType]  ?? 'cm';
            $thresholds  = $this->thresholdMap[$stationType] ?? ['alert' => 50.0, 'danger' => 100.0];

            $metadata = json_decode($station->metadata ?? '{}', true);
            $metadata['station_type']       = $stationType;
            $metadata['station_type_label'] = $station->station_type_label;
            $metadata['phone']              = $station->phone;
            $metadata['area']               = $station->area;
            $metadata['address']            = $station->address;

            $sensorId = DB::table('sensors')->insertGetId([
                'external_id'              => $station->external_id,
                'name'                     => $station->name,
                'type'                     => $sensorType,
                'district_id'              => $station->district_id,
                'status'                   => 'online',
                'unit'                     => $unit,
                'reading_interval_seconds' => 300, // 5 phút
                'alert_threshold'          => $thresholds['alert'],
                'danger_threshold'         => $thresholds['danger'],
                'last_value'               => $station->current_depth_m !== null
                                                ? (float)$station->current_depth_m * 100 // m → cm
                                                : null,
                'last_reading_at'          => $station->current_depth_m !== null ? now() : null,
                'is_active'                => true,
                'metadata'                 => json_encode($metadata),
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            // Copy PostGIS geometry từ sensor_stations
            if ($station->geometry) {
                DB::statement(
                    "UPDATE sensors SET geometry = ss.geometry
                     FROM sensor_stations ss
                     WHERE sensors.id = ? AND ss.id = ?",
                    [$sensorId, $station->id]
                );
            } elseif ($station->latitude && $station->longitude) {
                DB::statement(
                    "UPDATE sensors SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                    [$station->longitude, $station->latitude, $sensorId]
                );
            }

            $inserted++;
        }

        $this->command->info("✅ Sensors: {$inserted} trạm đã migrate từ sensor_stations (bỏ qua {$skipped} đã tồn tại)");
    }
}
