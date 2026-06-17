<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed tọa độ thực tế cho incidents, shelters, rescue_teams
 * Tọa độ thực tế tại Đà Nẵng
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->hasPostGIS()) {
            $this->command->warn('⚠️  PostGIS not available, skipping LocationSeeder geometry updates');

            return;
        }

        // ── Incidents ──────────────────────────────────────────────────────
        $incidents = [
            // Ngập đường Nguyễn Lương Bằng, Liên Chiểu
            ['title' => 'Ngập đường Nguyễn Lương Bằng', 'lat' => 16.0748, 'lng' => 108.1521],
            // Mưa lớn kéo dài quận Cẩm Lệ
            ['title' => 'Mưa lớn kéo dài quận Cẩm Lệ', 'lat' => 16.0102, 'lng' => 108.1893],
            // Cảnh báo sạt lở đồi Hòa Vang
            ['title' => 'Cảnh báo sạt lở đồi Hòa Vang', 'lat' => 15.9823, 'lng' => 108.0912],
            // Kẹt xe nghiêm trọng ngã tư Nguyễn Văn Linh - Hàm Nghi
            ['title' => 'Kẹt xe nghiêm trọng ngã tư Nguyễn Văn Linh - Hàm Nghi', 'lat' => 16.0612, 'lng' => 108.2122],
            // Nước hồ Thạc Gián có hiện tượng đổi màu
            ['title' => 'Nước hồ Thạc Gián có hiện tượng đổi màu', 'lat' => 16.0645, 'lng' => 108.2115],
            // Bãi rác tự phát gây cản trở thoát nước
            ['title' => 'Bãi rác tự phát gây cản trở thoát nước', 'lat' => 16.0655, 'lng' => 108.2102],
        ];

        foreach ($incidents as $i) {
            DB::table('incidents')
                ->where('title', $i['title'])
                ->update([
                    'geometry' => DB::raw("ST_SetSRID(ST_MakePoint({$i['lng']}, {$i['lat']}), 4326)"),
                ]);
        }
        $this->command->info('✅ Incidents locations updated');

        // ── Shelters ───────────────────────────────────────────────────────
        $shelters = [
            // Trường THPT Liên Chiểu — Liên Chiểu
            ['code' => 'SHELTER-001', 'lat' => 16.0712, 'lng' => 108.1498],
            // Trường ĐH Bách Khoa — Ngũ Hành Sơn
            ['code' => 'SHELTER-002', 'lat' => 16.0039, 'lng' => 108.2062],
            // Trung tâm VHTT Cẩm Lệ
            ['code' => 'SHELTER-003', 'lat' => 16.0134, 'lng' => 108.1921],
            // Sân vận động Hòa Vang
            ['code' => 'SHELTER-004', 'lat' => 15.9756, 'lng' => 108.1234],
        ];

        foreach ($shelters as $s) {
            DB::table('shelters')
                ->where('code', $s['code'])
                ->update([
                    'geometry' => DB::raw("ST_SetSRID(ST_MakePoint({$s['lng']}, {$s['lat']}), 4326)"),
                ]);
        }
        $this->command->info('✅ Shelters locations updated');

        // ── Rescue Teams ───────────────────────────────────────────────────
        $teams = [
            // Đội PCCC Liên Chiểu
            ['code' => 'RESCUE-001', 'lat' => 16.0698, 'lng' => 108.1467],
            // Đội PCCC Cẩm Lệ
            ['code' => 'RESCUE-002', 'lat' => 16.0089, 'lng' => 108.1876],
            // Đội Y tế Đà Nẵng — Hải Châu
            ['code' => 'RESCUE-003', 'lat' => 16.0678, 'lng' => 108.2208],
            // Đội quân đội Hòa Vang
            ['code' => 'RESCUE-004', 'lat' => 15.9801, 'lng' => 108.1156],
            // Đội tình nguyện Thanh Khê
            ['code' => 'RESCUE-005', 'lat' => 16.0589, 'lng' => 108.1934],
        ];

        foreach ($teams as $t) {
            DB::table('rescue_teams')
                ->where('code', $t['code'])
                ->update([
                    'current_latitude' => $t['lat'],
                    'current_longitude' => $t['lng'],
                    'last_location_update' => now(),
                ]);
        }
        $this->command->info('✅ Rescue teams locations updated');

        // ── Alerts ─────────────────────────────────────────────────────────
        // Gán tọa độ phân tán quanh Đà Nẵng cho tất cả alerts chưa có geometry
        $alertCoords = [
            [16.0678, 108.2208], // Hải Châu
            [16.0748, 108.1521], // Liên Chiểu
            [16.0102, 108.1893], // Cẩm Lệ
            [16.0039, 108.2062], // Ngũ Hành Sơn
            [16.0589, 108.1934], // Thanh Khê
            [15.9756, 108.1234], // Hòa Vang
            [16.0544, 108.2022], // Trung tâm
            [16.0412, 108.1756], // Cẩm Lệ Nam
        ];

        $alerts = DB::table('alerts')
            ->whereRaw('geometry IS NULL OR ST_IsEmpty(geometry)')
            ->orderBy('id')
            ->pluck('id');

        foreach ($alerts as $index => $alertId) {
            $coord = $alertCoords[$index % count($alertCoords)];
            // Thêm jitter nhỏ để không chồng lên nhau (dùng phép chia tỷ lệ thay vì modulo float trong PHP)
            $lat = $coord[0] + (($index * 31) % 200) / 10000 - 0.01;
            $lng = $coord[1] + (($index * 47) % 200) / 10000 - 0.01;
            DB::table('alerts')
                ->where('id', $alertId)
                ->update([
                    'geometry' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
                ]);
        }
        $this->command->info('✅ Alerts locations updated ('.count($alerts).' alerts)');
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
