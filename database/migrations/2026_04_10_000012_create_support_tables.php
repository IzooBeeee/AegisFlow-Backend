<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Relief supplies
        Schema::create('relief_supplies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 50);
            $table->string('unit', 20);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('min_stock_level', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('storage_location')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });

        // Supply stocks
        Schema::create('supply_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('relief_supplies')->cascadeOnDelete();
            $table->string('stockable_type', 50);
            $table->bigInteger('stockable_id');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->boolean('expiry_tracking')->default(false);
            $table->timestamps();

            $table->unique(['supply_id', 'stockable_type', 'stockable_id']);
            $table->index(['stockable_type', 'stockable_id']);
        });

        // Supply allocations
        Schema::create('supply_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('rescue_requests')->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained('relief_supplies')->cascadeOnDelete();
            $table->bigInteger('from_stock_id')->nullable();
            $table->bigInteger('to_shelter_id')->nullable();
            $table->bigInteger('to_team_id')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('request_id');
        });

        // Dashboard metrics
        Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 50);
            $table->foreignId('district_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('flood_zone_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('period_type', 20);
            $table->decimal('avg_value', 10, 4)->nullable();
            $table->decimal('max_value', 10, 4)->nullable();
            $table->decimal('min_value', 10, 4)->nullable();
            $table->integer('total_count')->nullable();
            $table->integer('active_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['metric_type', 'period_type', 'period_start']);
            $table->index(['district_id', 'flood_zone_id', 'period_start']);
        });

        // System settings
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('group_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('key');
            $table->index('group_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_metrics');
        Schema::dropIfExists('supply_allocations');
        Schema::dropIfExists('supply_stocks');
        Schema::dropIfExists('relief_supplies');
        Schema::dropIfExists('system_settings');
    }
};
