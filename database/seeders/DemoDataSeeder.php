<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\Alert;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\Prediction;
use App\Models\Recommendation;
use App\Models\RescueRequest;
use App\Models\RescueTeam;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Tắt broadcasting khi seed để tránh lỗi Reverb chưa chạy
        Event::fake();

        $this->command->info('🚀 Seeding demo data for pitch video...');

        $now = Carbon::now();

        // === 1. Create demo sensors with readings ===
        $this->seedDemoSensors($now);

        // === 2. Create demo flood zones ===
        $this->seedDemoFloodZones();

        // === 3. Create demo incidents ===
        $this->seedDemoIncidents($now);

        // === 4. Create demo predictions ===
        $this->seedDemoPredictions($now);

        // === 5. Create demo rescue requests ===
        $this->seedDemoRescueRequests($now);

        // === 6. Create demo alerts ===
        $this->seedDemoAlerts($now);

        // === 7. Create demo recommendations ===
        $this->seedDemoRecommendations($now);

        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->info('📊 Demo scenario: Da Nang flood event - April 23, 2026');
        $this->command->info('📊 Active flood zones: An Khê, Hòa Khánh, Túy Loan');
    }

    protected function seedDemoSensors(Carbon $now): void
    {
        $sensors = [
            ['external_id' => 'SENSOR-DN-001', 'name' => 'Trạm đo mực nước An Khê', 'type' => 'water_level', 'latitude' => 16.0606, 'longitude' => 108.1785, 'district_id' => 5, 'status' => 'online'],
            ['external_id' => 'SENSOR-DN-002', 'name' => 'Trạm đo mực nước Hòa Khánh', 'type' => 'water_level', 'latitude' => 16.0416, 'longitude' => 108.1052, 'district_id' => 1, 'status' => 'online'],
            ['external_id' => 'SENSOR-DN-003', 'name' => 'Trạm đo mực nước Túy Loan', 'type' => 'water_level', 'latitude' => 15.9933, 'longitude' => 108.1422, 'district_id' => 3, 'status' => 'online'],
            ['external_id' => 'SENSOR-DN-004', 'name' => 'Trạm đo mực nước Thanh Khê', 'type' => 'water_level', 'latitude' => 16.0681, 'longitude' => 108.1754, 'district_id' => 5, 'status' => 'online'],
            ['external_id' => 'SENSOR-DN-005', 'name' => 'Trạm đo mưa Hải Châu', 'type' => 'rainfall', 'latitude' => 16.0627, 'longitude' => 108.2232, 'district_id' => 4, 'status' => 'online'],
        ];

        foreach ($sensors as $sensorData) {
            $lat = $sensorData['latitude'];
            $lng = $sensorData['longitude'];
            unset($sensorData['latitude'], $sensorData['longitude']);

            $sensor = Sensor::updateOrCreate(
                ['external_id' => $sensorData['external_id']],
                array_merge($sensorData, [
                    'unit' => $sensorData['type'] === 'water_level' ? 'm' : 'mm',
                    'reading_interval_seconds' => 300,
                    'alert_threshold' => $sensorData['type'] === 'water_level' ? 1.5 : 80,
                    'danger_threshold' => $sensorData['type'] === 'water_level' ? 3.0 : 150,
                    'is_active' => true,
                ])
            );

            if (DB::connection()->getDriverName() === 'pgsql' && $this->hasPostGIS()) {
                DB::statement(
                    'UPDATE sensors SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $sensor->id]
                );
            }

            // Create readings for the past 6 hours
            for ($i = 6; $i >= 0; $i--) {
                $readingTime = $now->copy()->subHours($i);

                if ($sensorData['type'] === 'water_level') {
                    // Simulate rising water levels
                    $baseLevel = match ($sensorData['external_id']) {
                        'SENSOR-DN-001' => 1.8 + ($i <= 3 ? (3 - $i) * 0.3 : 0),  // Rising at An Khê
                        'SENSOR-DN-002' => 2.1 + ($i <= 3 ? (3 - $i) * 0.4 : 0),  // Rising at Hòa Khánh
                        'SENSOR-DN-003' => 1.5 + ($i <= 3 ? (3 - $i) * 0.2 : 0),  // Rising at Túy Loan
                        'SENSOR-DN-004' => 0.9 + ($i <= 3 ? (3 - $i) * 0.2 : 0),  // Rising at Thanh Khê
                        default => 1.0,
                    };
                    $value = $baseLevel + (rand(-10, 10) / 100);
                } else {
                    // Rainfall readings
                    $baseRain = match ($sensorData['external_id']) {
                        'SENSOR-DN-005' => 25.0 + ($i <= 3 ? (4 - $i) * 8 : 0),
                        default => 15.0 + ($i <= 3 ? (4 - $i) * 5 : 0),
                    };
                    $value = max(0, $baseRain + (rand(-5, 15) / 10));
                }

                SensorReading::firstOrCreate(
                    [
                        'sensor_id' => $sensor->id,
                        'recorded_at' => $readingTime,
                    ],
                    [
                        'value' => round($value, 3),
                        'source' => 'demo',
                        'quality_score' => 0.95,
                    ]
                );
            }

            $sensor->updateLastReading(round($value, 2), $now);
        }

        $this->command->info('   ✅ Sensors: '.count($sensors).' sensors with readings');
    }

    protected function seedDemoFloodZones(): void
    {
        $zones = [
            [
                'name' => 'Vùng ngập An Khê',
                'slug' => 'demo-an-khe',
                'risk_level' => 'high',
                'description' => 'Khu vực trũng thấp phường An Khê, quận Thanh Khê',
                'coordinates' => json_encode([
                    ['lat' => 16.058, 'lng' => 108.175],
                    ['lat' => 16.062, 'lng' => 108.180],
                    ['lat' => 16.065, 'lng' => 108.178],
                    ['lat' => 16.061, 'lng' => 108.172],
                ]),
                'district_id' => 5,
            ],
            [
                'name' => 'Vùng ngập Hòa Khánh',
                'slug' => 'demo-hoa-khanh',
                'risk_level' => 'critical',
                'description' => 'Khu vực trũng quận Liên Chiểu, gần cầu Trường Định',
                'coordinates' => json_encode([
                    ['lat' => 16.040, 'lng' => 108.100],
                    ['lat' => 16.048, 'lng' => 108.108],
                    ['lat' => 16.052, 'lng' => 108.105],
                    ['lat' => 16.044, 'lng' => 108.098],
                ]),
                'district_id' => 1,
            ],
            [
                'name' => 'Vùng ngập Túy Loan',
                'slug' => 'demo-tuy-loan',
                'risk_level' => 'medium',
                'description' => 'Khu vực nông thôn xã Hòa Phong, huyện Hòa Vang',
                'coordinates' => json_encode([
                    ['lat' => 15.990, 'lng' => 108.138],
                    ['lat' => 15.997, 'lng' => 108.145],
                    ['lat' => 16.001, 'lng' => 108.142],
                    ['lat' => 15.994, 'lng' => 108.136],
                ]),
                'district_id' => 3,
            ],
        ];

        foreach ($zones as $zoneData) {
            $coordinates = $zoneData['coordinates'];
            unset($zoneData['coordinates']);

            $zone = FloodZone::updateOrCreate(['slug' => $zoneData['slug']], array_merge($zoneData, [
                'status' => 'alert',
                'base_water_level_m' => 0,
                'current_water_level_m' => match ($zoneData['slug']) {
                    'demo-hoa-khanh' => 2.4,
                    'demo-an-khe' => 1.8,
                    default => 1.2,
                },
                'alert_threshold_m' => 1.5,
                'danger_threshold_m' => 3.0,
                'color' => match ($zoneData['risk_level']) {
                    'critical' => '#f04438',
                    'high' => '#f79009',
                    default => '#fdb022',
                },
                'opacity' => 0.35,
                'is_active' => true,
            ]));

            if (DB::connection()->getDriverName() === 'pgsql' && $this->hasPostGIS()) {
                $polygonPoints = collect(json_decode($coordinates, true));
                $polygonPoints->push($polygonPoints->first());

                $points = $polygonPoints
                    ->map(fn ($point) => "{$point['lng']} {$point['lat']}")
                    ->implode(', ');

                DB::statement(
                    'UPDATE flood_zones SET geometry = ST_GeomFromText(?, 4326), centroid = ST_Centroid(ST_GeomFromText(?, 4326)) WHERE id = ?',
                    ["POLYGON(($points))", "POLYGON(($points))", $zone->id]
                );
            }
        }

        $this->command->info('   ✅ Flood Zones: '.count($zones).' active zones');
    }

    protected function seedDemoIncidents(Carbon $now): void
    {
        $incidents = [
            [
                'title' => 'Mực nước vượt ngưỡng cảnh báo tại An Khê',
                'type' => 'flood',
                'severity' => 'high',
                'status' => 'responding',
                'source' => 'sensor',
                'address' => 'Khu vực An Khê, Thanh Khê, Đà Nẵng',
                'district_id' => 5,
                'latitude' => 16.0606,
                'longitude' => 108.1785,
                'water_level_m' => 1.8,
                'rainfall_mm' => 75.0,
                'description' => 'Mực nước đo được 1.8m, vượt ngưỡng cảnh báo 1.5m. Đang có xu hướng tăng.',
            ],
            [
                'title' => 'Ngập nặng đường Nguyễn Lương Bằng - Hòa Khánh',
                'type' => 'flood',
                'severity' => 'critical',
                'status' => 'responding',
                'source' => 'citizen',
                'address' => '123 Nguyễn Lương Bằng, Liên Chiểu, Đà Nẵng',
                'district_id' => 1,
                'latitude' => 16.0416,
                'longitude' => 108.1052,
                'water_level_m' => 0.6,
                'rainfall_mm' => 95.5,
                'description' => 'Nhiều hộ dân bị ngập, nước dâng cao 0.6m trên mặt đường. Có người mắc kẹt tầng trệt.',
            ],
            [
                'title' => 'Cảnh báo sớm Túy Loan - mưa lớn kéo dài',
                'type' => 'heavy_rain',
                'severity' => 'medium',
                'status' => 'verified',
                'source' => 'operator',
                'address' => 'Khu vực Túy Loan, Hòa Vang, Đà Nẵng',
                'district_id' => 3,
                'latitude' => 15.9933,
                'longitude' => 108.1422,
                'water_level_m' => 1.2,
                'rainfall_mm' => 120.0,
                'description' => 'Lượng mưa tích lũy 120mm/6h. Ngưỡng cảnh báo 100mm/6h. Cần theo dõi.',
            ],
            [
                'title' => 'Kẹt xe ngập úng tại Nút giao Nguyễn Văn Linh - Nguyễn Tri Phương',
                'type' => 'traffic',
                'severity' => 'medium',
                'status' => 'responding',
                'source' => 'citizen',
                'address' => 'Nút giao Nguyễn Văn Linh - Nguyễn Tri Phương, Hải Châu, Đà Nẵng',
                'district_id' => 4,
                'latitude' => 16.0602,
                'longitude' => 108.2091,
                'description' => 'Mưa lớn gây ngập nhẹ cục bộ tại ngã tư, các phương tiện di chuyển cực kỳ khó khăn, kẹt xe kéo dài đến cổng sân bay.',
            ],
            [
                'title' => 'Ô nhiễm dầu/rác thải tại Âu thuyền Thọ Quang',
                'type' => 'environment',
                'severity' => 'high',
                'status' => 'verified',
                'source' => 'operator',
                'address' => 'Âu thuyền Thọ Quang, Sơn Trà, Đà Nẵng',
                'district_id' => 6,
                'latitude' => 16.0988,
                'longitude' => 108.2435,
                'description' => 'Phát hiện vệt dầu tràn loang lổ kèm rác thải nhựa ứ đọng tại góc âu thuyền sau trận mưa lớn. Đang điều phối lực lượng xử lý.',
            ],
            [
                'title' => 'Rác thải lấp kín các miệng cống trên đường Hàm Nghi',
                'type' => 'trash',
                'severity' => 'high',
                'status' => 'responding',
                'source' => 'citizen',
                'address' => 'Đường Hàm Nghi (đoạn quanh hồ Thạc Gián), Thanh Khê, Đà Nẵng',
                'district_id' => 5,
                'latitude' => 16.0645,
                'longitude' => 108.2132,
                'description' => 'Lượng lớn lá cây và rác sinh hoạt bít kín toàn bộ miệng thu nước cống thoát nước, gây ngập úng cục bộ trên vỉa hè và lòng đường.',
            ],
        ];

        foreach ($incidents as $index => $incData) {
            $lat = $incData['latitude'];
            $lng = $incData['longitude'];
            unset($incData['latitude'], $incData['longitude']);

            $incident = Incident::updateOrCreate(
                ['title' => $incData['title']],
                array_merge($incData, [
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'created_at' => $now->copy()->subMinutes(45 + ($index * 15)),
                ])
            );

            if (DB::connection()->getDriverName() === 'pgsql' && $this->hasPostGIS()) {
                DB::statement(
                    'UPDATE incidents SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $incident->id]
                );
            }
        }

        $this->command->info('   ✅ Incidents: '.count($incidents).' active incidents');
    }

    protected function seedDemoPredictions(Carbon $now): void
    {
        $aiModel = AIModel::firstOrCreate(
            ['slug' => 'flood-risk-rf-v1'],
            [
                'name' => 'RandomForest Flood Risk Classifier',
                'model_type' => 'random_forest',
                'version' => 'v1.0.0',
                'framework' => 'scikit-learn',
                'output_type' => 'flood_risk',
                'is_production' => true,
                'is_active' => true,
            ]
        );

        $predictions = [
            [
                'prediction_type' => 'flood_risk',
                'severity' => 'critical',
                'predicted_value' => 82,
                'probability' => 0.85,
                'confidence' => 0.87,
                'flood_zone_id' => FloodZone::where('slug', 'demo-hoa-khanh')->first()?->id,
                'district_id' => 1,
                'input_data' => ['water_level_m' => 2.1, 'rainfall_mm' => 95.5, 'hours_rain' => 6],
            ],
            [
                'prediction_type' => 'flood_risk',
                'severity' => 'high',
                'predicted_value' => 68,
                'probability' => 0.72,
                'confidence' => 0.82,
                'flood_zone_id' => FloodZone::where('slug', 'demo-an-khe')->first()?->id,
                'district_id' => 5,
                'input_data' => ['water_level_m' => 1.8, 'rainfall_mm' => 75.0, 'hours_rain' => 5],
            ],
            [
                'prediction_type' => 'flood_risk',
                'severity' => 'medium',
                'predicted_value' => 45,
                'probability' => 0.55,
                'confidence' => 0.78,
                'flood_zone_id' => FloodZone::where('slug', 'demo-tuy-loan')->first()?->id,
                'district_id' => 3,
                'input_data' => ['water_level_m' => 1.2, 'rainfall_mm' => 55.0, 'hours_rain' => 4],
            ],
        ];

        foreach ($predictions as $index => $predData) {
            Prediction::updateOrCreate(
                [
                    'prediction_type' => $predData['prediction_type'],
                    'flood_zone_id' => $predData['flood_zone_id'],
                    'district_id' => $predData['district_id'],
                ],
                array_merge($predData, [
                    'model_id' => $aiModel->id,
                    'model_version' => 'v1.0.0',
                    'status' => 'generated',
                    'horizon_minutes' => 60,
                    'prediction_for' => $now->copy()->addMinutes(60),
                    'issued_at' => $now->copy()->subMinutes(5),
                    'created_at' => $now->copy()->subMinutes(5 + ($index * 2)),
                ])
            );
        }

        $this->command->info('   ✅ Predictions: '.count($predictions).' AI predictions');
    }

    protected function seedDemoRescueRequests(Carbon $now): void
    {
        $teams = RescueTeam::whereIn('code', ['RESCUE-001', 'RESCUE-002'])->get();

        $requests = [
            [
                'caller_name' => 'Nguyễn Thị Mai',
                'caller_phone' => '0901234567',
                'urgency' => 'critical',
                'category' => 'rescue',
                'people_count' => 4,
                'vulnerable_groups' => ['children', 'elderly'],
                'address' => '45 Nguyễn Lương Bằng, Liên Chiểu, Đà Nẵng',
                'latitude' => 16.0416,
                'longitude' => 108.1052,
                'district_id' => 1,
                'status' => 'pending',
                'water_level_m' => 0.8,
                'description' => 'Gia đình có 2 trẻ nhỏ (< 10 tuổi) và 1 bà cụ (72 tuổi). Nước đang dâng cao.',
            ],
            [
                'caller_name' => 'Trần Văn Hùng',
                'caller_phone' => '0912345678',
                'urgency' => 'high',
                'category' => 'medical',
                'people_count' => 2,
                'vulnerable_groups' => ['elderly'],
                'address' => '78 Đường số 3, An Khê, Thanh Khê',
                'latitude' => 16.0606,
                'longitude' => 108.1785,
                'district_id' => 5,
                'status' => 'pending',
                'water_level_m' => 0.6,
                'description' => 'Ông bà 68 tuổi, bệnh tim. Cần hỗ trợ y tế khẩn cấp.',
            ],
            [
                'caller_name' => 'Lê Thị Hương',
                'caller_phone' => '0934567890',
                'urgency' => 'medium',
                'category' => 'evacuation',
                'people_count' => 6,
                'vulnerable_groups' => [],
                'address' => 'Khu phố 5, Hòa Phong, Hòa Vang',
                'latitude' => 15.9933,
                'longitude' => 108.1422,
                'district_id' => 3,
                'status' => 'pending',
                'water_level_m' => 0.3,
                'description' => 'Gia đình 6 người muốn di chuyển đến nơi an toàn.',
            ],
        ];

        $priorityScores = [92, 78, 45];  // Calculated based on vulnerable groups, urgency, etc.

        foreach ($requests as $index => $reqData) {
            $lat = $reqData['latitude'];
            $lng = $reqData['longitude'];
            unset($reqData['latitude'], $reqData['longitude']);

            $req = RescueRequest::updateOrCreate(
                ['caller_phone' => $reqData['caller_phone']],
                array_merge($reqData, [
                    'request_number' => 'REQ-20260423-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'priority_score' => $priorityScores[$index],
                    'created_at' => $now->copy()->subMinutes(30 - ($index * 8)),
                ])
            );

            if (DB::connection()->getDriverName() === 'pgsql' && $this->hasPostGIS()) {
                DB::statement(
                    'UPDATE rescue_requests SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $req->id]
                );
            }
        }

        $this->command->info('   ✅ Rescue Requests: '.count($requests).' pending requests');
    }

    protected function seedDemoAlerts(Carbon $now): void
    {
        $alerts = [
            [
                'title' => '🚨 CẢNH BÁO NGẬP CẤP ĐỘ CAO - KHU VỰC HÒA KHÁNH',
                'alert_type' => 'flood_warning',
                'severity' => 'high',
                'description' => 'Mực nước tại khu vực Hòa Khánh đã vượt ngưỡng 2.0m. Nguy cơ ngập cao. Cư dân vùng trũng khẩn trương sơ tán.',
                'status' => 'active',
                'affected_districts' => [1],
            ],
            [
                'title' => '⚠️ CẢNH BÁO SỚM - NGUY CƠ NGẬP TẠI AN KHÊ',
                'alert_type' => 'flood_warning',
                'severity' => 'medium',
                'description' => 'Dự báo mực nước tiếp tục tăng trong 2 giờ tới. Người dân khu vực An Khê chú ý theo dõi và chuẩn bị phương án sơ tán.',
                'status' => 'active',
                'affected_districts' => [5],
            ],
            [
                'title' => '📢 THÔNG BÁO CHO ĐỘI CỨU HỘ - TRIỂN KHAI KHẨN',
                'alert_type' => 'rescue_dispatch',
                'severity' => 'high',
                'description' => 'Điều động Đội cứu hộ PCCC Liên Chiểu đến khu vực Hòa Khánh. Có 2 vụ yêu cầu cứu hộ khẩn cấp đang chờ.',
                'status' => 'active',
            ],
        ];

        foreach ($alerts as $index => $alertData) {
            $alertNumber = 'ALT-'.date('Ymd').'-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            Alert::updateOrCreate(
                ['title' => $alertData['title']],
                array_merge($alertData, [
                    'alert_number' => $alertNumber,
                    'issued_by' => User::first()?->id,
                    'source' => 'demo',
                    'effective_from' => $now->copy()->subMinutes(20 - ($index * 5)),
                    'effective_until' => $now->copy()->addHours(6),
                    'created_at' => $now->copy()->subMinutes(20 - ($index * 5)),
                ])
            );
        }

        $this->command->info('   ✅ Alerts: '.count($alerts).' active alerts');
    }

    protected function seedDemoRecommendations(Carbon $now): void
    {
        $incident = Incident::where('title', 'Ngập nặng đường Nguyễn Lương Bằng - Hòa Khánh')->first();
        $prediction = Prediction::where('flood_zone_id', FloodZone::where('slug', 'demo-hoa-khanh')->first()?->id)->first();

        $recommendations = [
            [
                'type' => 'rescue_dispatch',
                'description' => 'Điều động Đội PCCC Liên Chiểu (RESCUE-001) đến 45 Nguyễn Lương Bằng, Liên Chiểu. Ưu tiên: 4 người bao gồm 2 trẻ em và 1 người cao tuổi.',
                'status' => 'pending',
                'details' => [
                    'confidence_score' => 0.87,
                    'reasoning' => [
                        'urgency: critical (+30 pts)',
                        'vulnerable: children+elderly (+25 pts)',
                        'water_level: 0.8m (+10 pts)',
                        'wait_time: 25 min (+4 pts)',
                    ],
                ],
                'incident_id' => $incident?->id,
                'prediction_id' => $prediction?->id,
            ],
            [
                'type' => 'evacuation',
                'description' => 'Khuyến nghị sơ tán khu vực bán kính 500m quanh 45 Nguyễn Lương Bằng. Nguy cơ ngập mở rộng trong 30 phút tới.',
                'status' => 'pending',
                'details' => [
                    'confidence_score' => 0.82,
                    'reasoning' => [
                        'water_level_rising: +0.3m/h',
                        'predicted_reach: 500m in 30min',
                        'shelter_capacity: 800m away',
                    ],
                ],
                'incident_id' => $incident?->id,
                'prediction_id' => $prediction?->id,
            ],
            [
                'type' => 'alert',
                'description' => 'Gửi cảnh báo ngập mức HIGH đến 1,200 hộ dân khu vực Hòa Khánh qua ứng dụng di động.',
                'status' => 'approved',
                'details' => [
                    'confidence_score' => 0.95,
                    'reasoning' => [
                        'sensor_confirmed: water_level > 2.0m',
                        'multiple_reports: 3 citizen reports',
                        'prediction_confirmed: risk_score 82/100',
                    ],
                ],
                'incident_id' => $incident?->id,
                'prediction_id' => $prediction?->id,
            ],
        ];

        foreach ($recommendations as $index => $recData) {
            Recommendation::updateOrCreate(
                ['type' => $recData['type'], 'incident_id' => $recData['incident_id']],
                array_merge($recData, [
                    'created_at' => $now->copy()->subMinutes(15 - ($index * 3)),
                ])
            );
        }

        $this->command->info('   ✅ Recommendations: '.count($recommendations).' AI recommendations');
    }

    private function hasPostGIS(): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'postgis'");

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
