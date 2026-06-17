<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Không wrap trong transaction để PostGIS errors không rollback DDL
    public $withinTransaction = false;

    public function up(): void
    {
        // Drop existing tables if they exist (resilience)
        $this->dropGeographyTables();

        // Districts
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->bigInteger('population')->nullable();
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->timestamps();
        });

        // Wards
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->bigInteger('population')->nullable();
            $table->timestamps();
        });

        // Thêm PostGIS boundary (chỉ khi PostGIS đã được cài)
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
                DB::statement('ALTER TABLE districts ADD COLUMN IF NOT EXISTS boundary geometry(POLYGON, 4326)');
                DB::statement('ALTER TABLE wards ADD COLUMN IF NOT EXISTS boundary geometry(POLYGON, 4326)');
            } catch (Exception $e) {
                Log::warning('[Migration] PostGIS not available, skipping geometry columns: '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $this->dropGeographyTables();
    }

    private function dropGeographyTables(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS wards CASCADE');
            DB::statement('DROP TABLE IF EXISTS districts CASCADE');

            return;
        }

        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
    }
};
