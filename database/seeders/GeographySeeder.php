<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        // Quận/Huyện Đà Nẵng
        $districts = [
            ['name' => 'Liên Chiểu', 'code' => 'lien-chieu', 'population' => 230000, 'area_km2' => 252.87, 'risk_level' => 'high',
                'boundary' => 'POLYGON((108.10 16.10, 108.20 16.10, 108.20 16.05, 108.10 16.05, 108.10 16.10))'],
            ['name' => 'Cẩm Lệ', 'code' => 'cam-le', 'population' => 165000, 'area_km2' => 89.65, 'risk_level' => 'medium',
                'boundary' => 'POLYGON((108.15 16.02, 108.22 16.02, 108.22 15.97, 108.15 15.97, 108.15 16.02))'],
            ['name' => 'Hòa Vang', 'code' => 'hoa-vang', 'population' => 190000, 'area_km2' => 714.52, 'risk_level' => 'high',
                'boundary' => 'POLYGON((108.05 16.00, 108.25 16.00, 108.25 15.85, 108.05 15.85, 108.05 16.00))'],
            ['name' => 'Hải Châu', 'code' => 'hai-chau', 'population' => 205000, 'area_km2' => 28.36, 'risk_level' => 'low',
                'boundary' => 'POLYGON((108.16 16.08, 108.20 16.08, 108.20 16.04, 108.16 16.04, 108.16 16.08))'],
            ['name' => 'Thanh Khê', 'code' => 'thanh-khe', 'population' => 195000, 'area_km2' => 9.87, 'risk_level' => 'low',
                'boundary' => 'POLYGON((108.12 16.08, 108.17 16.08, 108.17 16.05, 108.12 16.05, 108.12 16.08))'],
            ['name' => 'Sơn Trà', 'code' => 'son-tra', 'population' => 165000, 'area_km2' => 59.52, 'risk_level' => 'low',
                'boundary' => 'POLYGON((108.20 16.12, 108.28 16.12, 108.28 16.05, 108.20 16.05, 108.20 16.12))'],
            ['name' => 'Ngũ Hành Sơn', 'code' => 'ngu-hanh-son', 'population' => 80000, 'area_km2' => 34.01, 'risk_level' => 'medium',
                'boundary' => 'POLYGON((108.23 16.00, 108.28 16.00, 108.28 15.95, 108.23 15.95, 108.23 16.00))'],
        ];

        $driver = DB::connection()->getDriverName();

        foreach ($districts as $district) {
            $boundaryWkt = $district['boundary'];
            unset($district['boundary']);

            $existing = DB::table('districts')->where('code', $district['code'])->first();

            if ($existing) {
                DB::table('districts')->where('id', $existing->id)->update($district);
                $id = $existing->id;
            } else {
                $id = DB::table('districts')->insertGetId($district);
            }

            if ($driver === 'pgsql') {
                DB::statement(
                    'UPDATE districts SET boundary = ST_GeomFromText(?, 4326) WHERE id = ?',
                    [$boundaryWkt, $id]
                );
            }
        }

        $this->command->info('✅ GeographySeeder: Đã tạo '.count($districts).' quận/huyện');
    }
}
