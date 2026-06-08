<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('incident_events')) {
            return;
        }

        Schema::table('incident_events', function (Blueprint $table) {
            if (!Schema::hasColumn('incident_events', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('incident_events')) {
            return;
        }

        Schema::table('incident_events', function (Blueprint $table) {
            if (Schema::hasColumn('incident_events', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
