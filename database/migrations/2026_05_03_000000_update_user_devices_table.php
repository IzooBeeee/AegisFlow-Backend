<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            // Rename device_token -> fcm_token
            $table->renameColumn('device_token', 'fcm_token');

            // Thêm các columns mới
            $table->string('device_model', 100)->nullable()->after('device_name');
            $table->string('os_version', 20)->nullable()->after('device_model');
            $table->boolean('notification_enabled')->default(true)->after('is_active');
            $table->json('notification_settings')->nullable()->after('notification_enabled');
            $table->timestamp('last_used_at')->nullable()->after('notification_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->renameColumn('fcm_token', 'device_token');
            $table->dropColumn([
                'device_model',
                'os_version',
                'notification_enabled',
                'notification_settings',
                'last_used_at',
            ]);
        });
    }
};
