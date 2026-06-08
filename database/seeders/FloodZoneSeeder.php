<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FloodZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Liên Chiểu',
                'slug' => 'lien-chieu',
                'description' => 'Khu vực trũng thấp, thường xuyên ngập khi mưa lớn',
                'risk_level' => 'critical',
                'status' => 'monitoring',
                'color' => '#f04438',
                'opacity' => 0.35,
                'base_water_level_m' => 0,
                'current_water_level_m' => 0.8,
                'alert_threshold_m' => 1.5,
                'danger_threshold_m' => 3.0,
                'geometry' => 'POLYGON((108.10 16.08, 108.14 16.08, 108.14 16.05, 108.10 16.05, 108.10 16.08))',
                'district_id' => 1,
            ],
            [
                'name' => 'Cẩm Lệ (Ven sông)',
                'slug' => 'cam-le',
                'description' => 'Khu vực ven sông Cẩm Lệ, thoát nước kém',
                'risk_level' => 'high',
                'status' => 'monitoring',
                'color' => '#f79009',
                'opacity' => 0.28,
                'base_water_level_m' => 0,
                'current_water_level_m' => 0.5,
                'alert_threshold_m' => 1.5,
                'danger_threshold_m' => 3.0,
                'geometry' => 'POLYGON((108.15 16.01, 108.19 16.01, 108.19 15.98, 108.15 15.98, 108.15 16.01))',
                'district_id' => 2,
            ],
            [
                'name' => 'Hoà Vang (Hòa Thọ Tây)',
                'slug' => 'hoa-vang',
                'description' => 'Khu vực trũng đô thị hóa nhanh, thiếu hạ tầng thoát nước',
                'risk_level' => 'high',
                'status' => 'monitoring',
                'color' => '#f79009',
                'opacity' => 0.24,
                'base_water_level_m' => 0,
                'current_water_level_m' => 0.3,
                'alert_threshold_m' => 1.5,
                'danger_threshold_m' => 3.0,
                'geometry' => 'POLYGON((108.08 15.98, 108.13 15.98, 108.13 15.94, 108.08 15.94, 108.08 15.98))',
                'district_id' => 3,
            ],
        ];

        $driver = DB::connection()->getDriverName();

        foreach ($zones as $zone) {
            $geometry = $zone['geometry'];
            unset($zone['geometry']);

            $zone['is_active'] = true;
            $zone['created_at'] = now();
            $zone['updated_at'] = now();

            $existing = DB::table('flood_zones')->where('slug', $zone['slug'])->first();

            if ($existing) {
                DB::table('flood_zones')->where('id', $existing->id)->update($zone);
                $id = $existing->id;
            } else {
                $id = DB::table('flood_zones')->insertGetId($zone);
            }

            if ($driver === 'pgsql') {
                DB::statement(
                    'UPDATE flood_zones SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?',
                    [$geometry, $id]
                );
            }
        }

        $this->command->info('✅ FloodZoneSeeder: Đã tạo '.count($zones).' vùng ngập');
    }
}
