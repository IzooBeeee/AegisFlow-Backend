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
        // Rescue teams
        Schema::create('rescue_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('team_type', 50);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->json('specializations')->nullable();
            $table->smallInteger('vehicle_count')->default(0);
            $table->smallInteger('personnel_count')->default(0);
            $table->json('equipment')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('available');
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->foreignId('heading_to_incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('team_type');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE rescue_teams ADD COLUMN IF NOT EXISTS current_location geometry(POINT, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for rescue_teams: ' . $e->getMessage());
            }
        }

        // Rescue members (pivot)
        Schema::create('rescue_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('rescue_teams')->cascadeOnDelete();
            $table->string('role', 50);
            $table->string('badge_number', 50)->nullable();
            $table->json('certifications')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
        });

        // Shelters
        Schema::create('shelters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('address');
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shelter_type', 50);
            $table->integer('capacity');
            $table->integer('current_occupancy')->default(0);
            $table->json('facilities')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('accessibility', 20)->default('accessible');
            $table->boolean('is_flood_safe')->default(true);
            $table->decimal('flood_depth_tolerance_m', 4, 2)->default(2.0);
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('opening_hours', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['district_id', 'status']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE shelters ADD COLUMN IF NOT EXISTS geometry geometry(POINT, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for shelters: ' . $e->getMessage());
            }
        }

        // Rescue requests
        Schema::create('rescue_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();
            $table->string('caller_name');
            $table->string('caller_phone', 20)->nullable();
            $table->string('address');
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('urgency', 20);
            $table->string('category', 50);
            $table->integer('people_count')->default(1);
            $table->json('vulnerable_groups')->nullable();
            $table->text('description')->nullable();
            $table->json('photo_urls')->nullable();
            $table->decimal('water_level_m', 6, 2)->nullable();
            $table->text('accessibility_notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('priority_score', 4, 2)->nullable();
            $table->foreignId('assigned_team_id')->nullable()->constrained('rescue_teams')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->integer('eta_minutes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('urgency');
            $table->index('priority_score');
            $table->index('created_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE rescue_requests ADD COLUMN IF NOT EXISTS geometry geometry(POINT, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for rescue_requests: ' . $e->getMessage());
            }
        }

        // Rescue request events
        Schema::create('rescue_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('rescue_requests')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->text('description')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_request_events');
        Schema::dropIfExists('rescue_requests');
        Schema::dropIfExists('shelters');
        Schema::dropIfExists('rescue_members');
        Schema::dropIfExists('rescue_teams');
    }
};
