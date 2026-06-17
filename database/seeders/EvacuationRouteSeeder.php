<?php

namespace Database\Seeders;

use App\Models\EvacuationRoute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EvacuationRouteSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo map_nodes cho các điểm tập kết / trú ẩn thực tế tại Đà Nẵng
        $nodes = [
            // Điểm xuất phát (vùng ngập)
            ['name' => 'Khu dân cư Liên Chiểu (điểm tập kết)',  'node_type' => 'shelter_point', 'district_id' => 1, 'latitude' => 16.0989, 'longitude' => 108.1506],
            ['name' => 'Ngã tư Hoàng Văn Thụ - Nguyễn Văn Linh', 'node_type' => 'intersection',   'district_id' => 2, 'latitude' => 16.0544, 'longitude' => 108.2022],
            ['name' => 'Chợ Cẩm Lệ',                              'node_type' => 'assembly_point', 'district_id' => 3, 'latitude' => 16.0231, 'longitude' => 108.2003],
            ['name' => 'Khu dân cư An Khê',                       'node_type' => 'assembly_point', 'district_id' => 2, 'latitude' => 16.0618, 'longitude' => 108.1982],
            ['name' => 'Khu công nghiệp Hòa Khánh (tập kết)',    'node_type' => 'assembly_point', 'district_id' => 1, 'latitude' => 16.0761, 'longitude' => 108.1444],
            ['name' => 'Ngã ba Túy Loan',                          'node_type' => 'intersection',   'district_id' => 5, 'latitude' => 15.9962, 'longitude' => 108.1531],

            // Điểm đến (nơi trú ẩn an toàn)
            ['name' => 'Trường THCS Lê Hồng Phong (trú ẩn)',     'node_type' => 'shelter',        'district_id' => 1, 'latitude' => 16.1143, 'longitude' => 108.1722],
            ['name' => 'Trung tâm thể thao Liên Chiểu (trú ẩn)', 'node_type' => 'shelter',        'district_id' => 1, 'latitude' => 16.1024, 'longitude' => 108.1680],
            ['name' => 'Trường ĐH Sư Phạm Đà Nẵng (trú ẩn)',    'node_type' => 'shelter',        'district_id' => 2, 'latitude' => 16.0740, 'longitude' => 108.2210],
            ['name' => 'Trường THPT Nguyễn Hiền (trú ẩn)',       'node_type' => 'shelter',        'district_id' => 3, 'latitude' => 16.0301, 'longitude' => 108.2150],
            ['name' => 'UBND Quận Thanh Khê (trú ẩn)',           'node_type' => 'shelter',        'district_id' => 4, 'latitude' => 16.0693, 'longitude' => 108.1902],
            ['name' => 'Trường THCS Nguyễn Bỉnh Khiêm (trú ẩn)','node_type' => 'shelter',        'district_id' => 5, 'latitude' => 16.0042, 'longitude' => 108.1711],
        ];

        $nodeIds = [];
        foreach ($nodes as $node) {
            $id = DB::table('map_nodes')->insertGetId([
                'name'        => $node['name'],
                'type'        => $node['node_type'],
                'district_id' => $node['district_id'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $nodeIds[] = $id;
        }

        // shelter_id lấy từ DB (dùng id đầu tiên có sẵn cho mỗi route)
        $shelterIds = DB::table('shelters')->pluck('id')->toArray();
        $floodZoneIds = DB::table('flood_zones')->pluck('id')->toArray();

        $routes = [
            [
                'name' => 'Tuyến sơ tán Liên Chiểu → Lê Hồng Phong',
                'description' => 'Tuyến chính từ khu vực ngập Liên Chiểu đến trường THCS Lê Hồng Phong',
                'start_node_id' => $nodeIds[0],
                'end_node_id'   => $nodeIds[6],
                'distance_m' => 3200,
                'estimated_time_seconds' => 960,
                'is_safe' => true,
                'safety_rating' => 0.92,
                'is_primary' => true,
                'max_capacity' => 500,
                'color' => '#10B981',
                'flood_zone_id' => $floodZoneIds[0] ?? null,
                'shelter_id' => $shelterIds[0] ?? null,
            ],
            [
                'name' => 'Tuyến sơ tán Liên Chiểu → Thể thao Liên Chiểu',
                'description' => 'Tuyến phụ trong quận Liên Chiểu, khoảng cách ngắn',
                'start_node_id' => $nodeIds[4],
                'end_node_id'   => $nodeIds[7],
                'distance_m' => 1800,
                'estimated_time_seconds' => 540,
                'is_safe' => true,
                'safety_rating' => 0.88,
                'is_primary' => false,
                'max_capacity' => 300,
                'color' => '#3B82F6',
                'flood_zone_id' => $floodZoneIds[4] ?? null,
                'shelter_id' => $shelterIds[1] ?? null,
            ],
            [
                'name' => 'Tuyến sơ tán An Khê → ĐH Sư Phạm',
                'description' => 'Từ khu vực ngập An Khê lên ĐH Sư Phạm trên cao',
                'start_node_id' => $nodeIds[3],
                'end_node_id'   => $nodeIds[8],
                'distance_m' => 2500,
                'estimated_time_seconds' => 750,
                'is_safe' => true,
                'safety_rating' => 0.85,
                'is_primary' => true,
                'max_capacity' => 400,
                'color' => '#10B981',
                'flood_zone_id' => $floodZoneIds[3] ?? null,
                'shelter_id' => $shelterIds[2] ?? null,
            ],
            [
                'name' => 'Tuyến sơ tán Cẩm Lệ → Nguyễn Hiền',
                'description' => 'Tuyến chính khu vực Cẩm Lệ ven sông Cẩm Lệ',
                'start_node_id' => $nodeIds[2],
                'end_node_id'   => $nodeIds[9],
                'distance_m' => 2100,
                'estimated_time_seconds' => 630,
                'is_safe' => true,
                'safety_rating' => 0.90,
                'is_primary' => true,
                'max_capacity' => 600,
                'color' => '#10B981',
                'flood_zone_id' => $floodZoneIds[1] ?? null,
                'shelter_id' => $shelterIds[3] ?? null,
            ],
            [
                'name' => 'Tuyến sơ tán Hoàng Văn Thụ → UBND Thanh Khê',
                'description' => 'Tuyến nội thành tránh ngập khu trung tâm',
                'start_node_id' => $nodeIds[1],
                'end_node_id'   => $nodeIds[10],
                'distance_m' => 1600,
                'estimated_time_seconds' => 480,
                'is_safe' => true,
                'safety_rating' => 0.87,
                'is_primary' => false,
                'max_capacity' => 350,
                'color' => '#F59E0B',
                'flood_zone_id' => null,
                'shelter_id' => $shelterIds[4] ?? null,
            ],
            [
                'name' => 'Tuyến sơ tán Túy Loan → Nguyễn Bỉnh Khiêm',
                'description' => 'Tuyến vùng ngoại thành Hòa Vang thoát lên khu cao',
                'start_node_id' => $nodeIds[5],
                'end_node_id'   => $nodeIds[11],
                'distance_m' => 4200,
                'estimated_time_seconds' => 1260,
                'is_safe' => true,
                'safety_rating' => 0.80,
                'is_primary' => true,
                'max_capacity' => 250,
                'color' => '#10B981',
                'flood_zone_id' => $floodZoneIds[5] ?? null,
                'shelter_id' => $shelterIds[5] ?? null,
            ],
        ];

        foreach ($routes as $route) {
            DB::table('evacuation_routes')->insert(array_merge($route, [
                'risk_factors' => json_encode(['flood', 'road_closure']),
                'current_usage' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('Seeded ' . count($routes) . ' evacuation routes and ' . count($nodes) . ' map nodes.');
    }
}
