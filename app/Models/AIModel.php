<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AIModel — Phiên bản mô hình AI
 */
class AIModel extends Model
{
    use HasFactory;

    protected $table = 'ai_models';

    protected $fillable = [
        'name',
        'slug',
        'model_type',
        'version',
        'description',
        'framework',
        'input_features',
        'output_type',
        'training_dataset_id',
        'performance_metrics',
        'hyperparameters',
        'trained_at',
        'deployed_at',
        'is_active',
        'is_production',
    ];

    protected $casts = [
        'input_features' => 'array',
        'performance_metrics' => 'array',
        'hyperparameters' => 'array',
        'trained_at' => 'datetime',
        'deployed_at' => 'datetime',
        'is_active' => 'boolean',
        'is_production' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'model_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function deploy(): self
    {
        // Deactivate other production models of same type
        static::where('output_type', $this->output_type)
            ->where('is_production', true)
            ->update(['is_production' => false]);

        $this->is_active = true;
        $this->is_production = true;
        $this->deployed_at = now();
        $this->save();

        return $this;
    }

    public function deactivate(): self
    {
        $this->is_active = false;
        $this->is_production = false;
        $this->save();

        return $this;
    }

    public function scopeProduction($query)
    {
        return $query->where('is_production', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
