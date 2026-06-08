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
        DB::statement('SET session_replication_role = replica');
        DB::table('recommendations')->truncate();
        DB::table('alerts')->truncate();
        DB::table('predictions')->truncate();
        DB::table('rescue_requests')->truncate();
        DB::table('incidents')->truncate();
        DB::table('sensors')->truncate();
        DB::table('rescue_teams')->truncate();
        DB::table('shelters')->truncate();
        DB::table('flood_zones')->truncate();
        DB::table('districts')->truncate();
        DB::table('ai_models')->truncate();
        DB::statement('SET session_replication_role = DEFAULT');

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
