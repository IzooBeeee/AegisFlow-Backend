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
        Schema::create('flood_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ward_ids')->nullable();
            $table->string('risk_level', 20);
            $table->decimal('base_water_level_m', 6, 2)->default(0);
            $table->decimal('current_water_level_m', 6, 2)->default(0);
            $table->decimal('alert_threshold_m', 6, 2)->default(1.5);
            $table->decimal('danger_threshold_m', 6, 2)->default(3.0);
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->integer('population_affected')->nullable();
            $table->string('status', 20)->default('monitoring');
            $table->string('color', 7)->default('#f79009');
            $table->decimal('opacity', 3, 2)->default(0.3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Thêm PostGIS geometry (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE flood_zones ADD COLUMN IF NOT EXISTS geometry geometry(POLYGON, 4326)');
                DB::statement('ALTER TABLE flood_zones ADD COLUMN IF NOT EXISTS centroid geometry(POINT, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for flood_zones: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_zones');
    }
};
