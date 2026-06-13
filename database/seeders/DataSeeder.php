<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AIModel;
use App\Models\Incident;
use App\Models\Prediction;
use App\Models\RescueRequest;
use App\Models\RescueTeam;
use App\Models\Shelter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataSeeder extends Seeder
{
    private const SAFE_INCIDENT_COORDINATES = [
        ['lat' => 16.0712, 'lng' => 108.1498], // Liên Chiểu
        ['lat' => 16.0645, 'lng' => 108.2115], // Thanh Khê / Hải Châu
        ['lat' => 16.0612, 'lng' => 108.2122], // Nguyễn Văn Linh - Hàm Nghi
        ['lat' => 16.0134, 'lng' => 108.1921], // Cẩm Lệ
        ['lat' => 16.0039, 'lng' => 108.2062], // Ngũ Hành Sơn
        ['lat' => 15.9823, 'lng' => 108.0912], // Hòa Vang
        ['lat' => 16.0589, 'lng' => 108.1934], // Thanh Khê
        ['lat' => 16.0678, 'lng' => 108.2208], // Hải Châu
        ['lat' => 16.0102, 'lng' => 108.1893], // Cẩm Lệ
        ['lat' => 16.0412, 'lng' => 108.1756], // Cẩm Lệ Nam
    ];

    public function run(): void
    {
        // Truncate đã được xử lý ở DatabaseSeeder

        $this->seedShelters();
        $this->seedRescueTeams();
        $this->seedAIModels();
        $this->seedIncidents();
        $this->seedRescueRequests();
    }

    protected function seedShelters(): void
    {
        $shelters = [
            ['name' => 'Trường THPT Liên Chiểu', 'code' => 'SHELTER-001', 'address' => '123 Nguyễn Lương Bằng, Liên Chiểu, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 500, 'current_occupancy' => 45, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 1],
            ['name' => 'Trường ĐH Bách Khoa', 'code' => 'SHELTER-002', 'address' => '54 Nguyễn Lương Bằng, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 1000, 'current_occupancy' => 120, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet', 'shelter'], 'is_flood_safe' => true, 'district_id' => 2],
            ['name' => 'Trung tâm VHTT Cẩm Lệ', 'code' => 'SHELTER-003', 'address' => '456 Đường 2/9, Cẩm Lệ, Đà Nẵng', 'shelter_type' => 'community_center', 'capacity' => 300, 'current_occupancy' => 20, 'facilities' => ['food', 'water', 'medical'], 'is_flood_safe' => true, 'district_id' => 2],
            ['name' => 'Sân vận động Hòa Vang', 'code' => 'SHELTER-004', 'address' => '78 Quang Trung, Hòa Vang, Đà Nẵng', 'shelter_type' => 'stadium', 'capacity' => 2000, 'current_occupancy' => 0, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 3],
            // Added Shelters
            ['name' => 'Nhà Thi đấu Liên Chiểu', 'code' => 'SHELTER-005', 'address' => 'Đường số 5, KCN Hòa Khánh, Liên Chiểu, Đà Nẵng', 'shelter_type' => 'stadium', 'capacity' => 800, 'current_occupancy' => 300, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 1],
            ['name' => 'Trường THCS Lê Anh Xuân', 'code' => 'SHELTER-006', 'address' => '25 Tôn Đản, Hòa An, Cẩm Lệ, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 350, 'current_occupancy' => 150, 'facilities' => ['food', 'water', 'electricity'], 'is_flood_safe' => true, 'district_id' => 2],
            ['name' => 'Trường Tiểu học Trần Cao Vân', 'code' => 'SHELTER-007', 'address' => '213 Lê Duẩn, Thanh Khê, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 450, 'current_occupancy' => 10, 'facilities' => ['food', 'water', 'electricity', 'medical'], 'is_flood_safe' => true, 'district_id' => 5],
            ['name' => 'Nhà Văn hóa Phường Thạch Thang', 'code' => 'SHELTER-008', 'address' => '23 Quang Trung, Hải Châu, Đà Nẵng', 'shelter_type' => 'community_center', 'capacity' => 200, 'current_occupancy' => 195, 'facilities' => ['water', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 4],
            ['name' => 'Trường THPT Phan Châu Trinh', 'code' => 'SHELTER-009', 'address' => '154 Lê Lợi, Hải Châu, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 1200, 'current_occupancy' => 150, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 4],
            ['name' => 'Trung tâm Hội chợ Triển lãm Đà Nẵng', 'code' => 'SHELTER-010', 'address' => '9 Cách Mạng Tháng Tám, Khuê Trung, Cẩm Lệ', 'shelter_type' => 'stadium', 'capacity' => 3000, 'current_occupancy' => 0, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 2],
            ['name' => 'Trường Tiểu học Ngô Gia Tự', 'code' => 'SHELTER-011', 'address' => '102 Trần Phú, Sơn Trà, Đà Nẵng', 'shelter_type' => 'school', 'capacity' => 300, 'current_occupancy' => 250, 'facilities' => ['food', 'water', 'toilet'], 'is_flood_safe' => true, 'district_id' => 6],
            ['name' => 'UBND Phường Nại Hiên Đông', 'code' => 'SHELTER-012', 'address' => 'Đường Trần Thánh Tông, Sơn Trà', 'shelter_type' => 'community_center', 'capacity' => 150, 'current_occupancy' => 100, 'facilities' => ['water', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 6],
            ['name' => 'Trường Đại học Ngoại Ngữ', 'code' => 'SHELTER-013', 'address' => '131 Lương Nhữ Hộc, Hải Châu', 'shelter_type' => 'school', 'capacity' => 1500, 'current_occupancy' => 400, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 4],
            ['name' => 'Ký túc xá DMC 579', 'code' => 'SHELTER-014', 'address' => 'Doãn Uẩn, Khuê Mỹ, Ngũ Hành Sơn', 'shelter_type' => 'community_center', 'capacity' => 5000, 'current_occupancy' => 1200, 'facilities' => ['food', 'water', 'medical', 'electricity', 'toilet'], 'is_flood_safe' => true, 'district_id' => 7],
        ];

        foreach ($shelters as $s) {
            Shelter::firstOrCreate(['code' => $s['code']], array_merge($s, [
                'status' => 'open',
                'is_flood_safe' => true,
            ]));
        }

        // Tạo thêm 40 điểm ngẫu nhiên bằng Faker
        $faker = \Faker\Factory::create('vi_VN');
        $types = ['school', 'community_center', 'stadium', 'religious', 'government'];
        $facilities_list = ['food', 'water', 'medical', 'electricity', 'toilet', 'shelter'];
        
        $added = 0;
        for ($i = 15; $i <= 54; $i++) {
            $code = 'SHELTER-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            if (Shelter::where('code', $code)->exists()) continue;
            
            $cap = $faker->numberBetween(100, 2000);
            Shelter::create([
                'name' => 'Điểm sơ tán ' . $faker->streetName(),
                'code' => $code,
                'address' => $faker->streetAddress() . ', ' . $faker->randomElement(['Liên Chiểu', 'Cẩm Lệ', 'Hải Châu', 'Sơn Trà', 'Thanh Khê', 'Ngũ Hành Sơn', 'Hòa Vang']) . ', Đà Nẵng',
                'shelter_type' => $faker->randomElement($types),
                'capacity' => $cap,
                'current_occupancy' => $faker->numberBetween(0, $cap),
                'facilities' => $faker->randomElements($facilities_list, $faker->numberBetween(2, 6)),
                'is_flood_safe' => true,
                'district_id' => $faker->numberBetween(1, 7),
                'status' => $faker->randomElement(['open', 'open', 'open', 'full', 'closed']),
            ]);
            $added++;
        }

        $this->command->info('✅ Shelters: ' . (count($shelters) + $added));
    }

    protected function seedRescueTeams(): void
    {
        $teams = [
            ['name' => 'Đội cứu hộ PCCC Liên Chiểu', 'code' => 'RESCUE-001',
             'team_type' => 'fire', 'district_id' => 1,
             'specializations' => ['flood_rescue', 'first_aid', 'water_pump'],
             'personnel_count' => 25, 'vehicle_count' => 5, 'status' => 'available'],
            ['name' => 'Đội cứu hộ PCCC Cẩm Lệ', 'code' => 'RESCUE-002',
             'team_type' => 'fire', 'district_id' => 2,
             'specializations' => ['flood_rescue', 'first_aid'],
             'personnel_count' => 20, 'vehicle_count' => 4, 'status' => 'available'],
            ['name' => 'Đội Y tế Đà Nẵng', 'code' => 'RESCUE-003',
             'team_type' => 'medical', 'district_id' => null,
             'specializations' => ['first_aid', 'medical_evacuation', 'triage'],
             'personnel_count' => 30, 'vehicle_count' => 6, 'status' => 'available'],
            ['name' => 'Đội quân đội Hòa Vang', 'code' => 'RESCUE-004',
             'team_type' => 'military', 'district_id' => 3,
             'specializations' => ['flood_rescue', 'logistics', 'evacuation'],
             'personnel_count' => 50, 'vehicle_count' => 10, 'status' => 'available'],
            ['name' => 'Đội tình nguyện Thanh Khê', 'code' => 'RESCUE-005',
             'team_type' => 'volunteer', 'district_id' => 5,
             'specializations' => ['food_distribution', 'shelter_support', 'first_aid'],
             'personnel_count' => 40, 'vehicle_count' => 2, 'status' => 'available'],
        ];

        foreach ($teams as $t) {
            RescueTeam::firstOrCreate(['code' => $t['code']], $t);
        }

        $this->command->info('✅ RescueTeams: '.count($teams));
    }

    protected function seedAIModels(): void
    {
        $models = [
            ['name' => 'LSTM Water Level Predictor', 'slug' => 'water-level-lstm',
             'model_type' => 'lstm', 'version' => 'v1.0', 'framework' => 'TensorFlow',
             'output_type' => 'water_level', 'description' => 'Dự báo mực nước bằng LSTM',
             'input_features' => ['sensor_readings_24h', 'weather_data', 'historical_flood'],
             'is_production' => true],
            ['name' => 'CNN Rainfall Classifier', 'slug' => 'rainfall-cnn',
             'model_type' => 'cnn', 'version' => 'v1.2', 'framework' => 'PyTorch',
             'output_type' => 'rainfall', 'description' => 'Phân loại cường độ mưa',
             'input_features' => ['radar_image', 'satellite_image'],
             'is_production' => false],
            ['name' => 'Flood Risk Ensemble', 'slug' => 'flood-risk-ensemble',
             'model_type' => 'ensemble', 'version' => 'v1.0', 'framework' => 'Scikit-learn',
             'output_type' => 'flood_probability', 'description' => 'Ensemble model dự báo ngập',
             'input_features' => ['water_level', 'rainfall', 'tide_level', 'topography'],
             'is_production' => true],
            ['name' => 'Rule-based Alert Engine', 'slug' => 'alert-rule-engine',
             'model_type' => 'rule', 'version' => 'v2.0', 'framework' => 'Python',
             'output_type' => 'alert', 'description' => 'Engine sinh cảnh báo từ rules',
             'input_features' => ['thresholds', 'sensor_data'],
             'is_production' => true],
        ];

        foreach ($models as $m) {
            AIModel::firstOrCreate(['slug' => $m['slug']], array_merge($m, [
                'is_active' => $m['is_production'],
            ]));
        }

        $this->command->info('✅ AIModels: '.count($models));
    }

    protected function seedIncidents(): void
    {
        $incidents = [
            // Original flood-focused incidents
            ['title' => 'Ngập đường Nguyễn Lương Bằng', 'type' => 'flood', 'severity' => 'high', 'status' => 'responding', 'source' => 'citizen', 'address' => '123 Nguyễn Lương Bằng, Liên Chiểu', 'district_id' => 1, 'water_level_m' => 0.8, 'reported_by' => 1],
            ['title' => 'Mưa lớn kéo dài quận Cẩm Lệ', 'type' => 'heavy_rain', 'severity' => 'medium', 'status' => 'verified', 'source' => 'sensor', 'address' => 'Khu vực Cẩm Lệ', 'district_id' => 2, 'rainfall_mm' => 85.5, 'reported_by' => 1],
            ['title' => 'Cảnh báo sạt lở đồi Hòa Vang', 'type' => 'landslide', 'severity' => 'critical', 'status' => 'responding', 'source' => 'operator', 'address' => 'Đường đèo Hòa Vang', 'district_id' => 3, 'reported_by' => 2],
            ['title' => 'Ngập ngã tư Nguyễn Văn Linh - Hàm Nghi', 'type' => 'flood', 'severity' => 'medium', 'status' => 'reported', 'source' => 'citizen', 'address' => 'Ngã tư Nguyễn Văn Linh - Hàm Nghi, Hải Châu', 'district_id' => 4, 'reported_by' => 1],
            ['title' => 'Nước tràn hồ Thạc Gián', 'type' => 'flood', 'severity' => 'high', 'status' => 'verified', 'source' => 'citizen', 'address' => 'Hồ Thạc Gián, Thanh Khê', 'district_id' => 5, 'reported_by' => 1],
            // Added Flood Incidents
            ['title' => 'Ngập nặng KDC Phước Lý', 'type' => 'flood', 'severity' => 'critical', 'status' => 'responding', 'source' => 'citizen', 'address' => 'KDC Phước Lý, Hòa Minh, Liên Chiểu', 'district_id' => 1, 'water_level_m' => 1.2, 'reported_by' => 1],
            ['title' => 'Ngập sâu đường Mẹ Suốt', 'type' => 'flood', 'severity' => 'critical', 'status' => 'responding', 'source' => 'citizen', 'address' => 'Đường Mẹ Suốt, Hòa Khánh Nam', 'district_id' => 1, 'water_level_m' => 1.5, 'reported_by' => 1],
            ['title' => 'Nước dâng cao đường Trường Chinh', 'type' => 'flood', 'severity' => 'high', 'status' => 'verified', 'source' => 'sensor', 'address' => 'Đoạn qua cầu vượt ngã ba Huế', 'district_id' => 5, 'water_level_m' => 0.6, 'reported_by' => 2],
            ['title' => 'Mưa xối xả khu vực Sơn Trà', 'type' => 'heavy_rain', 'severity' => 'high', 'status' => 'reported', 'source' => 'citizen', 'address' => 'Bán đảo Sơn Trà', 'district_id' => 6, 'rainfall_mm' => 120.0, 'reported_by' => 1],
            ['title' => 'Sạt lở đất đá bán đảo Sơn Trà', 'type' => 'landslide', 'severity' => 'critical', 'status' => 'verified', 'source' => 'operator', 'address' => 'Đường lên đỉnh Bàn Cờ', 'district_id' => 6, 'reported_by' => 2],
            ['title' => 'Đường Tôn Đức Thắng ngập nửa bánh xe', 'type' => 'flood', 'severity' => 'medium', 'status' => 'reported', 'source' => 'citizen', 'address' => 'Bến xe Trung tâm', 'district_id' => 1, 'water_level_m' => 0.4, 'reported_by' => 1],
            ['title' => 'Ngập cục bộ đường Nguyễn Văn Linh', 'type' => 'flood', 'severity' => 'high', 'status' => 'responding', 'source' => 'sensor', 'address' => 'Gần sân bay Đà Nẵng', 'district_id' => 4, 'water_level_m' => 0.7, 'reported_by' => 1],
            ['title' => 'Nước ngập tràn vào nhà dân', 'type' => 'flood', 'severity' => 'critical', 'status' => 'reported', 'source' => 'citizen', 'address' => 'Kiệt 161 Mẹ Suốt', 'district_id' => 1, 'water_level_m' => 1.8, 'reported_by' => 1],
            ['title' => 'Lũ quét sông Túy Loan', 'type' => 'flood', 'severity' => 'critical', 'status' => 'responding', 'source' => 'operator', 'address' => 'Xã Hòa Phong, Hòa Vang', 'district_id' => 3, 'water_level_m' => 2.5, 'reported_by' => 2],
            ['title' => 'Ngập lụt bờ kè Sông Hàn', 'type' => 'flood', 'severity' => 'medium', 'status' => 'verified', 'source' => 'sensor', 'address' => 'Đường Bạch Đằng', 'district_id' => 4, 'water_level_m' => 0.3, 'reported_by' => 1],
            ['title' => 'Mưa lớn gây ngập Chợ Cồn', 'type' => 'heavy_rain', 'severity' => 'high', 'status' => 'responding', 'source' => 'citizen', 'address' => 'Ngã tư Hùng Vương - Ông Ích Khiêm', 'district_id' => 4, 'rainfall_mm' => 90.0, 'reported_by' => 1],
            ['title' => 'Ngập đường tránh Nam Hải Vân', 'type' => 'flood', 'severity' => 'high', 'status' => 'verified', 'source' => 'operator', 'address' => 'Khu vực Suối Lương', 'district_id' => 1, 'water_level_m' => 0.8, 'reported_by' => 2],
        ];

        foreach ($incidents as $i) {
            Incident::firstOrCreate(
                ['title' => $i['title']],
                array_merge($i, ['created_at' => now()->subHours(rand(1, 48))])
            );
        }

        // Tạo thêm 50 sự cố ngập lụt bằng Faker
        $faker = \Faker\Factory::create('vi_VN');
        $addedIncidents = 0;
        for ($i = 0; $i < 50; $i++) {
            $severity = $faker->randomElement(['low', 'medium', 'high', 'critical']);
            $wl = $faker->randomFloat(1, 0.2, 2.5);
            $coordinate = self::SAFE_INCIDENT_COORDINATES[$i % count(self::SAFE_INCIDENT_COORDINATES)];
            $incident = Incident::create([
                'title' => 'Ngập đường ' . $faker->streetName(),
                'type' => 'flood',
                'severity' => $severity,
                'status' => $faker->randomElement(['reported', 'verified', 'responding', 'resolved']),
                'source' => $faker->randomElement(['citizen', 'sensor', 'operator']),
                'address' => $faker->streetAddress() . ', Đà Nẵng',
                'district_id' => $faker->numberBetween(1, 7),
                'water_level_m' => $wl,
                'reported_by' => $faker->numberBetween(1, 5),
                'created_at' => now()->subHours(rand(1, 72))
            ]);

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    'UPDATE incidents SET geometry = ST_SetSRID(ST_MakePoint(?::numeric, ?::numeric), 4326) WHERE id = ?',
                    [$coordinate['lng'], $coordinate['lat'], $incident->id]
                );
            }

            $addedIncidents++;
        }

        $this->command->info('✅ Incidents: ' . (count($incidents) + $addedIncidents));
    }

    protected function seedRescueRequests(): void
    {
        $requests = [
            ['caller_name' => 'Nguyễn Thị Lan', 'urgency' => 'high', 'category' => 'rescue', 'people_count' => 3, 'vulnerable_groups' => ['children'], 'address' => '45 Đường số 5, Liên Chiểu', 'district_id' => 1, 'status' => 'pending', 'reported_by' => 5],
            ['caller_name' => 'Trần Văn Minh', 'urgency' => 'medium', 'category' => 'food', 'people_count' => 10, 'address' => '78 Phố Hòa Khánh, Cẩm Lệ', 'district_id' => 2, 'status' => 'assigned', 'reported_by' => 5],
            ['caller_name' => 'Lê Hoàng Nam', 'urgency' => 'critical', 'category' => 'medical', 'people_count' => 1, 'vulnerable_groups' => ['elderly'], 'address' => '12 Đường 2/9, Hải Châu', 'district_id' => 4, 'status' => 'in_progress', 'reported_by' => 4],
            // Added requests
            ['caller_name' => 'Hoàng Thị Cúc', 'urgency' => 'critical', 'category' => 'rescue', 'people_count' => 4, 'vulnerable_groups' => ['elderly', 'children'], 'address' => 'Kiệt 161 Mẹ Suốt, Hòa Khánh Nam', 'district_id' => 1, 'status' => 'pending', 'reported_by' => 5],
            ['caller_name' => 'Đặng Văn Cường', 'urgency' => 'high', 'category' => 'rescue', 'people_count' => 2, 'vulnerable_groups' => ['disabled'], 'address' => 'KDC Phước Lý', 'district_id' => 1, 'status' => 'assigned', 'reported_by' => 5],
            ['caller_name' => 'Phạm Thu Thủy', 'urgency' => 'medium', 'category' => 'food', 'people_count' => 15, 'vulnerable_groups' => [], 'address' => 'Trường Mầm Non Họa Mi, Sơn Trà', 'district_id' => 6, 'status' => 'pending', 'reported_by' => 4],
            ['caller_name' => 'Bùi Tuấn Anh', 'urgency' => 'critical', 'category' => 'medical', 'people_count' => 1, 'vulnerable_groups' => ['pregnant'], 'address' => '230 Tôn Đản, Cẩm Lệ', 'district_id' => 2, 'status' => 'in_progress', 'reported_by' => 4],
            ['caller_name' => 'Võ Thị Hồng', 'urgency' => 'high', 'category' => 'rescue', 'people_count' => 5, 'vulnerable_groups' => ['children'], 'address' => 'Xã Hòa Phong', 'district_id' => 3, 'status' => 'pending', 'reported_by' => 5],
            ['caller_name' => 'Đinh Công Trứ', 'urgency' => 'medium', 'category' => 'evacuation', 'people_count' => 8, 'vulnerable_groups' => ['elderly'], 'address' => '54 Nguyễn Tất Thành', 'district_id' => 5, 'status' => 'completed', 'reported_by' => 5],
            ['caller_name' => 'Trịnh Ngọc Sơn', 'urgency' => 'high', 'category' => 'medical', 'people_count' => 2, 'vulnerable_groups' => ['injured'], 'address' => 'Đường lên bán đảo Sơn Trà', 'district_id' => 6, 'status' => 'assigned', 'reported_by' => 4],
        ];

        foreach ($requests as $index => $r) {
            RescueRequest::firstOrCreate(
                ['caller_name' => $r['caller_name'], 'address' => $r['address']],
                array_merge($r, [
                    'request_number' => 'REQ-' . date('Ymd') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'created_at' => now()->subHours(rand(1, 12))
                ])
            );
        }

        // Tạo thêm 50 yêu cầu cứu hộ bằng Faker
        $faker = \Faker\Factory::create('vi_VN');
        $addedRequests = 0;
        $baseIndex = count($requests) + 1;
        for ($i = 0; $i < 50; $i++) {
            $vg = [];
            if ($faker->boolean(30)) $vg[] = 'children';
            if ($faker->boolean(30)) $vg[] = 'elderly';
            if ($faker->boolean(10)) $vg[] = 'pregnant';
            if ($faker->boolean(10)) $vg[] = 'disabled';

            RescueRequest::create([
                'request_number' => 'REQ-' . date('Ymd') . '-' . str_pad($baseIndex + $i, 4, '0', STR_PAD_LEFT),
                'caller_name' => $faker->name(),
                'urgency' => $faker->randomElement(['low', 'medium', 'high', 'critical']),
                'category' => $faker->randomElement(['rescue', 'food', 'medical', 'evacuation']),
                'people_count' => $faker->numberBetween(1, 20),
                'vulnerable_groups' => $vg,
                'address' => $faker->streetAddress() . ', Đà Nẵng',
                'district_id' => $faker->numberBetween(1, 7),
                'status' => $faker->randomElement(['pending', 'assigned', 'in_progress', 'completed']),
                'reported_by' => $faker->numberBetween(1, 5),
                'created_at' => now()->subHours(rand(1, 48))
            ]);
            $addedRequests++;
        }

        $this->command->info('✅ RescueRequests: ' . (count($requests) + $addedRequests));
    }
}
