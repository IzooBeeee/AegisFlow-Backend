<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Trạm đo thực tế từ hệ thống muangap Đà Nẵng
        Schema::create('sensor_stations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 50)->unique()->nullable(); // code từ muangap
            $table->string('external_object_id', 50)->nullable();   // MongoDB _id
            $table->string('name');
            $table->string('phone', 30)->nullable();                 // số điện thoại trạm
            $table->string('station_type', 30);                      // flood_tower/water_level/flood_warning_tower/rain_station/reservoir_waterlevel
            $table->string('station_type_label', 50)->nullable();    // Tháp báo ngập / Trạm đo mực nước...
            $table->string('area', 100)->nullable();                 // tên khu vực
            $table->string('address')->nullable();
            $table->string('ward_name', 100)->nullable();
            $table->string('district_name', 100)->nullable();
            $table->string('city', 50)->default('Đà Nẵng');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('current_depth_m', 7, 3)->nullable();    // mực nước hiện tại (m)
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('station_type');
            $table->index('district_id');
            $table->index('is_active');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE sensor_stations ADD COLUMN IF NOT EXISTS geometry geometry(POINT, 4326)');
                DB::statement('CREATE INDEX idx_sensor_stations_geometry ON sensor_stations USING GIST (geometry)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for sensor_stations: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_stations');
    }
};
