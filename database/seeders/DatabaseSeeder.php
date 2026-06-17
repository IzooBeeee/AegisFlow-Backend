<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Xóa data cũ trước khi seed lại (tránh duplicate)
        DB::statement('TRUNCATE TABLE notifications, recommendations, alerts, predictions, rescue_requests, incidents, sensors, rescue_teams, shelters, flood_zones, districts, ai_models RESTART IDENTITY CASCADE');

        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            GeographySeeder::class,
            FloodZoneSeeder::class,
            DataSeeder::class,
            RealDataSeeder::class,
            DemoDataSeeder::class,
            LocationSeeder::class,
        ]);
    }
}
