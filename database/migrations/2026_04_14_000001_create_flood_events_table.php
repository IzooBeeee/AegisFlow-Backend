<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Đợt lũ lịch sử (ví dụ: FLOOD-14102022)
        Schema::create('flood_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 50)->unique()->nullable(); // FLOOD-14102022
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('severity', 20)->default('moderate'); // minor/moderate/severe/extreme
            $table->string('cause', 50)->nullable();            // heavy_rain/typhoon/storm_surge
            $table->string('status', 20)->default('historical'); // active/historical
            $table->integer('total_reports')->default(0);
            $table->integer('affected_districts')->default(0);
            $table->decimal('max_water_level_cm', 7, 2)->nullable();
            $table->decimal('avg_water_level_cm', 7, 2)->nullable();
            $table->decimal('total_rainfall_mm', 7, 2)->nullable();
            $table->integer('people_affected')->nullable();
            $table->integer('houses_flooded')->nullable();
            $table->json('affected_district_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index('severity');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_events');
    }
};
