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
        // Map nodes (trước edges vì edges FK tới nodes)
        Schema::create('map_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->nullable();
            $table->string('name')->nullable();
            $table->string('type', 50);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('elevation_m', 6, 2)->nullable();
            $table->foreignId('flood_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_safe')->default(true);
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
        });

        // Thêm PostGIS point
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE map_nodes ADD COLUMN IF NOT EXISTS geometry geometry(POINT, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for map_nodes: ' . $e->getMessage());
            }
        }

        // Map edges
        Schema::create('map_edges', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->nullable();
            $table->string('name')->nullable();
            $table->foreignId('source_node_id')->constrained('map_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('map_nodes')->cascadeOnDelete();
            $table->decimal('length_m', 10, 2);
            $table->smallInteger('lanes')->default(2);
            $table->smallInteger('max_speed_kmh')->nullable();
            $table->string('road_type', 50)->default('local');
            $table->string('direction', 10)->default('two_way');
            $table->string('surface_type', 30)->nullable();
            $table->boolean('has_sidewalk')->default(true);
            $table->boolean('is_flood_prone')->default(false);

            // Metrics realtime
            $table->decimal('current_density', 5, 4)->default(0);
            $table->decimal('current_speed_kmh', 6, 2)->default(0);
            $table->decimal('current_flow', 8, 2)->nullable();
            $table->string('congestion_level', 20)->default('none');
            $table->string('status', 20)->default('normal');
            $table->timestamp('metrics_updated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_node_id', 'target_node_id']);
            $table->index('status');
            $table->index('congestion_level');
            $table->index('is_flood_prone');
        });

        // Thêm PostGIS linestring
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE map_edges ADD COLUMN IF NOT EXISTS geometry geometry(LINESTRING, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for map_edges: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('map_edges');
        Schema::dropIfExists('map_nodes');
    }
};
