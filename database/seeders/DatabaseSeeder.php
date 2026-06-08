<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            GeographySeeder::class,
            FloodZoneSeeder::class,
            DataSeeder::class,
            RealDataSeeder::class,  // Data thực tế từ muangap.danang.gov.vn
            DemoDataSeeder::class,  // Seed dữ liệu demo cho video pitch
            LocationSeeder::class,
        ]);
    }
}
