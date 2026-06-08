<?php

namespace Database\Seeders;

use App\Support\DaNangLandMask;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealDataSeeder extends Seeder
{
    private string $dataDir;

    private string $driver;

    public function run(): void
    {
        $this->dataDir = base_path('../scripts/crawled_data');
        $this->driver = DB::connection()->getDriverName();

        $this->seedSensorStations();
        $this->seedFloodEvent2022();
        $this->seedFloodReports2022();
        $this->seedRainStations();
    }

    // ─── 1. Trạm đo thực tế (93 trạm từ muangap) ───────────────────────────

    protected function seedSensorStations(): void
    {
        $file = $this->dataDir.'/water_stations_all.json';
        if (! file_exists($file)) {
            $this->command->warn('⚠️  water_stations_all.json not found, skipping sensor_stations');

            return;
        }

        $stations = json_decode(file_get_contents($file), true);
        $types = $this->loadStationTypes();

        $inserted = 0;
        foreach ($stations as $s) {
            $typeId = $s['waterStationTypeId'] ?? null;
            $typeInfo = $types[$typeId] ?? ['code' => 'unknown', 'title' => 'Unknown'];
            $lat = $s['latitude'] ?? null;
            $lng = $s['longitude'] ?? null;

            $districtId = $this->resolveDistrictId($s['district'] ?? $s['area'] ?? '');

            $row = [
                'external_id' => $s['code'] ?? null,
                'external_object_id' => $s['id'] ?? null,
                'name' => $s['name'],
                'phone' => $s['number'] ?? null,
                'station_type' => $typeInfo['code'],
                'station_type_label' => $typeInfo['title'],
                'area' => $s['area'] ?? null,
                'address' => $s['address'] ?? null,
                'ward_name' => $s['district'] ?? null,
                'district_name' => $s['district'] ?? null,
                'city' => $s['city'] ?? 'Đà Nẵng',
                'latitude' => $lat,
                'longitude' => $lng,
                'current_depth_m' => isset($s['depth']) ? (float) $s['depth'] : null,
                'status' => 'active',
                'is_active' => true,
                'district_id' => $districtId,
                'metadata' => json_encode(['source' => 'muangap', 'raw_id' => $s['id'] ?? null]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('sensor_stations')->upsert($row, ['external_id'], array_keys($row));

            // PostGIS geometry
            if ($this->driver === 'pgsql' && $lat && $lng) {
                $id = DB::table('sensor_stations')->where('external_id', $s['code'])->value('id');
                if ($id) {
                    DB::statement(
                        'UPDATE sensor_stations SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                        [$lng, $lat, $id]
                    );
                }
            }
            $inserted++;
        }

        $this->command->info("✅ SensorStations: {$inserted} trạm đo thực tế");
    }

    // ─── 2. Đợt lũ lịch sử 14/10/2022 ──────────────────────────────────────

    protected function seedFloodEvent2022(): void
    {
        $file = $this->dataDir.'/flood_reports_2022_oct14.json';
        if (! file_exists($file)) {
            $this->command->warn('⚠️  flood_reports_2022_oct14.json not found');

            return;
        }

        $reports = json_decode(file_get_contents($file), true);

        // Tính thống kê
        $waterLevels = array_filter(array_column($reports, 'water_level'));
        $districts = [];
        foreach ($reports as $r) {
            $d = $r['location']['district_name'] ?? null;
            if ($d) {
                $districts[$d] = true;
            }
        }

        DB::table('flood_events')->upsert([
            'external_id' => 'FLOOD-14102022',
            'name' => 'Đợt ngập lụt 14/10/2022 - Đà Nẵng',
            'description' => 'Đợt mưa lớn kéo dài gây ngập nặng tại nhiều quận huyện TP. Đà Nẵng ngày 14/10/2022. Ghi nhận '.count($reports).' điểm ngập từ hệ thống báo cáo cộng đồng.',
            'started_at' => '2022-10-14 00:00:00',
            'ended_at' => '2022-10-15 06:00:00',
            'severity' => 'severe',
            'cause' => 'heavy_rain',
            'status' => 'historical',
            'total_reports' => count($reports),
            'affected_districts' => count($districts),
            'max_water_level_cm' => $waterLevels ? max($waterLevels) : null,
            'avg_water_level_cm' => $waterLevels ? round(array_sum($waterLevels) / count($waterLevels), 1) : null,
            'affected_district_ids' => json_encode(array_keys($districts)),
            'metadata' => json_encode(['source' => 'muangap', 'period_id' => 'FLOOD-14102022']),
            'created_at' => now(),
            'updated_at' => now(),
        ], ['external_id'], ['name', 'description', 'total_reports', 'max_water_level_cm', 'avg_water_level_cm', 'updated_at']);

        $this->command->info('✅ FloodEvent: Đợt lũ 14/10/2022 đã được tạo');
    }

    // ─── 3. 392 báo cáo ngập 14/10/2022 ────────────────────────────────────

    protected function seedFloodReports2022(): void
    {
        $file = $this->dataDir.'/flood_reports_2022_oct14.json';
        if (! file_exists($file)) {
            return;
        }

        $reports = json_decode(file_get_contents($file), true);
        $eventId = DB::table('flood_events')->where('external_id', 'FLOOD-14102022')->value('id');

        $batch = [];
        $skipped = 0;

        foreach ($reports as $r) {
            $loc = $r['location'] ?? [];
            $coords = $loc['coordinates'] ?? [null, null];
            $lng = $coords[0] ?? null;
            $lat = $coords[1] ?? null;

            // Bỏ qua records không có tọa độ hợp lệ
            if (! $lat || ! $lng || $lat == 0 || $lng == 0 || ! DaNangLandMask::isLikelyLand((float) $lat, (float) $lng)) {
                $skipped++;

                continue;
            }

            $floodTime = $r['flood_time'] ?? [];
            $startTs = isset($floodTime['start_time']) ? Carbon::createFromTimestamp($floodTime['start_time']) : null;
            $endTs = isset($floodTime['end_time']) ? Carbon::createFromTimestamp($floodTime['end_time']) : null;
            $createTs = isset($r['create_time']) ? Carbon::createFromTimestamp($r['create_time']) : now();

            $districtId = $this->resolveDistrictId($loc['district_name'] ?? '');

            $images = [];
            foreach ($r['images'] ?? [] as $img) {
                if (! empty($img['img_url'])) {
                    $images[] = $img['img_url'];
                }
            }

            $batch[] = [
                'external_id' => $r['_id'] ?? null,
                'flood_event_id' => $eventId,
                'district_id' => $districtId,
                'address' => $loc['address'] ?? null,
                'street_name' => $loc['street_name'] ?? null,
                'ward_name' => $loc['ward_name'] ?? null,
                'district_name' => $loc['district_name'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'flood_type' => $r['flood_type'] ?? 'point',
                'water_level_cm' => $r['water_level'] ?? null,
                'flood_unit' => $r['flood_unit'] ?? 'cm',
                'flood_location_type' => $r['flood_location_details'] ?? null,
                'is_frequent' => (bool) ($r['is_frequent'] ?? false),
                'description' => $r['flood_description'] ?? null,
                'image_urls' => json_encode($images),
                'polyline' => $r['polyline'] ?? null,
                'flood_started_at' => $startTs,
                'flood_ended_at' => $endTs,
                'reported_at' => $createTs,
                'status' => 'verified',
                'source' => 'citizen',
                'metadata' => json_encode(['source' => 'muangap', 'flood_status' => $r['floodStatus'] ?? null]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert theo batch 50
            if (count($batch) >= 50) {
                $this->insertFloodReportBatch($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->insertFloodReportBatch($batch);
        }

        $total = count($reports) - $skipped;
        $this->command->info("✅ FloodReports: {$total} báo cáo ngập 14/10/2022 (bỏ qua {$skipped} không có tọa độ)");
    }

    private function insertFloodReportBatch(array $batch): void
    {
        DB::table('flood_reports')->upsert($batch, ['external_id'], array_diff(
            array_keys($batch[0]),
            ['external_id', 'created_at']
        ));

        // Cập nhật PostGIS geometry
        if ($this->driver === 'pgsql') {
            foreach ($batch as $row) {
                if ($row['latitude'] && $row['longitude'] && $row['external_id']) {
                    DB::statement(
                        'UPDATE flood_reports SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE external_id = ?',
                        [$row['longitude'], $row['latitude'], $row['external_id']]
                    );
                }
            }
        }
    }

    // ─── 4. 82 trạm đo mưa ──────────────────────────────────────────────────

    protected function seedRainStations(): void
    {
        $file = $this->dataDir.'/rain_stations.json';
        if (! file_exists($file)) {
            return;
        }

        $data = json_decode(file_get_contents($file), true);
        $stations = $data['data'] ?? $data;

        $inserted = 0;
        foreach ($stations as $s) {
            $coords = $s['location']['coordinates'] ?? [null, null];
            $lng = $coords[0] ?? null;
            $lat = $coords[1] ?? null;

            $row = [
                'external_id' => $s['code'] ?? null,
                'external_object_id' => $s['id'] ?? null,
                'name' => $s['name'],
                'phone' => $s['number'] ?? null,
                'station_type' => 'rain_station',
                'station_type_label' => 'Trạm đo mưa',
                'area' => $s['area'] ?? null,
                'district_name' => $s['area'] ?? null,
                'city' => $s['city'] ?? 'Đà Nẵng',
                'latitude' => $lat,
                'longitude' => $lng,
                'current_depth_m' => isset($s['depth']) ? (float) $s['depth'] : null,
                'status' => 'active',
                'is_active' => true,
                'district_id' => null,
                'metadata' => json_encode(['source' => 'muangap', 'total_24h' => $s['total_depth_24_hours'] ?? 0]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('sensor_stations')->upsert($row, ['external_id'], array_keys($row));

            if ($this->driver === 'pgsql' && $lat && $lng) {
                $id = DB::table('sensor_stations')->where('external_id', $s['code'])->value('id');
                if ($id) {
                    DB::statement(
                        'UPDATE sensor_stations SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                        [$lng, $lat, $id]
                    );
                }
            }
            $inserted++;
        }

        $this->command->info("✅ RainStations: {$inserted} trạm đo mưa");
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function loadStationTypes(): array
    {
        $file = $this->dataDir.'/water_station_types.json';
        if (! file_exists($file)) {
            return [];
        }

        $types = json_decode(file_get_contents($file), true);
        $result = [];
        foreach ($types as $t) {
            $result[$t['_id']] = ['code' => $t['code'], 'title' => $t['title']];
        }

        return $result;
    }

    private function resolveDistrictId(string $name): ?int
    {
        if (! $name) {
            return null;
        }

        static $cache = [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        // Normalize: bỏ "Quận ", "Huyện ", "Thành phố "
        $clean = preg_replace('/^(Quận|Huyện|Thành phố|TP\.?)\s+/iu', '', $name);

        $id = DB::table('districts')
            ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($clean).'%'])
            ->value('id');

        $cache[$name] = $id;

        return $id;
    }
}
