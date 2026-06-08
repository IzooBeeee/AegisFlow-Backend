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
        // Alerts
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_number', 30)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('alert_type', 50);
            $table->string('severity', 20);
            $table->string('status', 20)->default('active');
            $table->json('affected_districts')->nullable();
            $table->json('affected_wards')->nullable();
            $table->json('affected_flood_zones')->nullable();
            $table->decimal('radius_km', 6, 2)->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('source', 30)->default('system');
            $table->timestamps();

            $table->index('status');
            $table->index('severity');
            $table->index('alert_type');
            $table->index('effective_from');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE alerts ADD COLUMN IF NOT EXISTS geometry geometry(GEOMETRY, 4326)');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Migration] PostGIS unavailable for alerts: ' . $e->getMessage());
            }
        }

        // Thêm FK sau khi bảng incidents tồn tại
        if (Schema::hasTable('incidents')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->foreignId('related_prediction_id')->nullable()->after('source')
                    ->constrained('predictions')->nullOnDelete();
                $table->foreignId('related_incident_id')->nullable()->after('related_prediction_id')
                    ->constrained('incidents')->nullOnDelete();
            });
        }

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('notification_type', 50);
            $table->string('target_type', 20);
            $table->bigInteger('target_id')->nullable();
            $table->json('target_roles')->nullable();
            $table->json('target_districts')->nullable();
            $table->string('channel', 20)->default('fcm');
            $table->string('status', 20)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->integer('total_sent')->default(0);
            $table->integer('total_delivered')->default(0);
            $table->integer('total_read')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index(['target_type', 'target_id']);
        });

        // User devices
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('device_token');
            $table->string('device_type', 20);
            $table->string('device_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'device_token']);
            $table->index('device_token');
        });

        // Notification delivery logs
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('user_devices')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('notification_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('alerts');
    }
};
