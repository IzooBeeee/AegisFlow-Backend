<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI Datasets
        Schema::create('ai_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('record_count')->nullable();
            $table->json('features')->nullable();
            $table->json('labels')->nullable();
            $table->date('train_start_date')->nullable();
            $table->date('train_end_date')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('name');
        });

        // AI Models
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('model_type', 50);
            $table->string('version', 20);
            $table->text('description')->nullable();
            $table->string('framework', 50)->nullable();
            $table->json('input_features')->default('[]');
            $table->string('output_type', 50);
            $table->foreignId('training_dataset_id')->nullable()->constrained('ai_datasets')->nullOnDelete();
            $table->json('performance_metrics')->nullable();
            $table->json('hyperparameters')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_production')->default(false);
            $table->timestamps();

            $table->index('is_production');
            $table->index('output_type');
        });

        // Predictions
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->string('model_version', 20)->nullable();
            $table->string('prediction_type', 50);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flood_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('prediction_for');
            $table->integer('horizon_minutes');
            $table->timestamp('issued_at')->useCurrent();
            $table->decimal('predicted_value', 10, 4)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->decimal('probability', 5, 4)->nullable();
            $table->string('severity', 20)->nullable();
            $table->json('input_data')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->string('status', 20)->default('generated');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('prediction_type');
            $table->index('issued_at');
            $table->index('flood_zone_id');
        });

        if (Schema::hasTable('incidents')) {
            Schema::table('predictions', function (Blueprint $table) {
                $table->foreignId('incident_id')->nullable()->after('model_id')
                    ->constrained()->nullOnDelete();
            });
        }

        // Prediction details
        Schema::create('prediction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->bigInteger('entity_id');
            $table->decimal('predicted_value', 10, 4)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->decimal('probability', 5, 4)->nullable();
            $table->string('severity', 20)->nullable();
            $table->json('risk_factors')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('prediction_id');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_details');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('ai_datasets');
        Schema::dropIfExists('ai_models');
    }
};
