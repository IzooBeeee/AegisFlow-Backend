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
        // Báo cáo ngập từ người dân (crowdsourced từ muangap)
        Schema::create('flood_reports', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 50)->unique()->nullable(); // MongoDB _id từ muangap
            $table->foreignId('flood_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();

            // Vị trí
            $table->string('address')->nullable();
            $table->string('street_name')->nullable();
            $table->string('ward_name')->nullable();
            $table->string('district_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Thông tin ngập
            $table->string('flood_type', 20)->default('point'); // point/street
            $table->decimal('water_level_cm', 7, 2)->nullable();
            $table->string('flood_unit', 10)->default('cm');
            $table->string('flood_location_type', 30)->nullable(); // house/street/reportOutside/reportInHome
            $table->boolean('is_frequent')->default(false);
            $table->text('description')->nullable();
            $table->json('image_urls')->nullable();
            $table->string('polyline', 2000)->nullable(); // encoded polyline cho street flood

            // Thời gian
            $table->timestamp('flood_started_at')->nullable();
            $table->timestamp('flood_ended_at')->nullable();
            $table->timestamp('reported_at');

            // Trạng thái
            $table->string('status', 20)->default('verified'); // pending/verified/resolved
            $table->string('source', 20)->default('citizen'); // citizen/sensor/operator
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('flood_event_id');
            $table->index('district_id');
            $table->index('reported_at');
            $table->index('flood_type');
            $table->index('is_frequent');
        });

        // Thêm PostGIS geometry
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE flood_reports ADD COLUMN IF NOT EXISTS geometry geometry(POINT, 4326)');
                DB::statement('CREATE INDEX idx_flood_reports_geometry ON flood_reports USING GIST (geometry)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for flood_reports: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_reports');
    }
};
