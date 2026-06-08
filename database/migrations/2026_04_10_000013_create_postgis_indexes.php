<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Tạo PostGIS spatial indexes và trigger functions
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            // Enable PostGIS extension
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS extension not available, skipping spatial indexes: ' . $e->getMessage());
            return;
        }

        // Spatial indexes cho PostGIS columns
        DB::statement('CREATE INDEX IF NOT EXISTS idx_flood_zones_geom ON flood_zones USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_districts_geom ON districts USING GIST(boundary)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_wards_geom ON wards USING GIST(boundary)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sensors_geom ON sensors USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_incidents_geom ON incidents USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_map_nodes_geom ON map_nodes USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_map_edges_geom ON map_edges USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_shelters_geom ON shelters USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_evacuation_routes_geom ON evacuation_routes USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_alerts_geom ON alerts USING GIST(geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_rescue_teams_location ON rescue_teams USING GIST(current_location) WHERE current_location IS NOT NULL');

        // Trigger: auto-update updated_at
        DB::statement("
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        $tables = [
            'users', 'districts', 'wards', 'flood_zones',
            'sensors', 'incidents', 'map_nodes', 'map_edges',
            'shelters', 'rescue_teams', 'rescue_members',
            'rescue_requests', 'evacuation_routes', 'ai_models',
            'predictions', 'recommendations', 'alerts',
            'notifications', 'relief_supplies', 'supply_stocks',
            'supply_allocations', 'system_settings', 'user_devices',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP TRIGGER IF EXISTS trigger_{$table}_updated_at ON {$table}");
            DB::statement("
                CREATE TRIGGER trigger_{$table}_updated_at
                BEFORE UPDATE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
            ");
        }

        // Trigger: auto-update centroid cho flood_zones
        DB::statement("
            CREATE OR REPLACE FUNCTION update_flood_zone_centroid()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.centroid = ST_Centroid(NEW.geometry);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        if (Schema::hasTable('flood_zones')) {
            DB::statement("DROP TRIGGER IF EXISTS trigger_flood_zone_centroid ON flood_zones");
            DB::statement("
                CREATE TRIGGER trigger_flood_zone_centroid
                BEFORE INSERT OR UPDATE OF geometry ON flood_zones
                FOR EACH ROW EXECUTE FUNCTION update_flood_zone_centroid()
            ");
        }

        // Tạo partition cho sensor_readings (tháng hiện tại + tháng tới)
        $currentYear = date('Y');
        $currentMonth = date('m');
        $nextMonth = date('m', strtotime('+1 month'));
        $nextYear = date('Y', strtotime('+1 month'));

        $partitionName = "sensor_readings_y{$currentYear}m{$currentMonth}";
        $nextPartition = "sensor_readings_y{$nextYear}m{$nextMonth}";

        $startDate = "{$currentYear}-{$currentMonth}-01";
        $endDate = date('Y-m-d', strtotime($startDate.' +1 month'));
        $nextStart = "{$nextYear}-{$nextMonth}-01";
        $nextEnd = date('Y-m-d', strtotime($nextStart.' +1 month'));

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$partitionName}
            PARTITION OF sensor_readings
            FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$nextPartition}
            PARTITION OF sensor_readings
            FOR VALUES FROM ('{$nextStart}') TO ('{$nextEnd}')
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'flood_zones', 'districts', 'wards', 'sensors',
            'incidents', 'map_nodes', 'map_edges', 'shelters',
            'rescue_teams', 'evacuation_routes', 'alerts',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP INDEX IF EXISTS idx_{$table}_geom");
        }

        DB::statement('DROP INDEX IF EXISTS idx_rescue_teams_location');
        DB::statement('DROP INDEX IF EXISTS idx_sensors_geom');
    }
};
