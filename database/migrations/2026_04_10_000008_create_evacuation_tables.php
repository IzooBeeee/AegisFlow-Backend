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
        // Evacuation routes
        Schema::create('evacuation_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('start_node_id')->constrained('map_nodes')->cascadeOnDelete();
            $table->foreignId('end_node_id')->constrained('map_nodes')->cascadeOnDelete();
            $table->integer('distance_m');
            $table->integer('estimated_time_seconds');
            $table->boolean('is_safe')->default(true);
            $table->decimal('safety_rating', 3, 2)->nullable();
            $table->json('risk_factors')->nullable();
            $table->foreignId('flood_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shelter_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->integer('max_capacity')->nullable();
            $table->integer('current_usage')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_safe');
            $table->index('flood_zone_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE evacuation_routes ADD COLUMN IF NOT EXISTS geometry geometry(LINESTRING, 4326)');
                DB::statement('ALTER TABLE evacuation_routes ADD COLUMN IF NOT EXISTS polyline TEXT');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for evacuation_routes: ' . $e->getMessage());
            }
        }

        // Evacuation route segments
        Schema::create('evacuation_route_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('evacuation_routes')->cascadeOnDelete();
            $table->foreignId('edge_id')->constrained('map_edges')->cascadeOnDelete();
            $table->smallInteger('sequence_order');
            $table->boolean('is_flood_prone')->default(false);
            $table->string('risk_level', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['route_id', 'sequence_order']);
            $table->index('route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evacuation_route_segments');
        Schema::dropIfExists('evacuation_routes');
    }
};
