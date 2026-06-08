<?php

namespace App\Models;

use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Prediction — Kết quả dự đoán lũ
 */
class Prediction extends Model
{
    use HasFactory, HasTranslatedEnums;

    protected $fillable = [
        'model_id',
        'incident_id',
        'model_version',
        'prediction_type',
        'target_area',
        'district_id',
        'flood_zone_id',
        'prediction_for',
        'horizon_minutes',
        'issued_at',
        'predicted_value',
        'confidence',
        'probability',
        'severity',
        'input_data',
        'processing_time_ms',
        'status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'prediction_for' => 'datetime',
        'issued_at' => 'datetime',
        'verified_at' => 'datetime',
        'predicted_value' => 'decimal:4',
        'confidence' => 'decimal:2',
        'probability' => 'decimal:4',
        'input_data' => 'array',
        'processing_time_ms' => 'integer',
    ];

    protected static array $translatedEnums = [
        'status' => 'enums.prediction_status',
        'severity' => 'enums.severity',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function model(): BelongsTo
    {
        return $this->belongsTo(AIModel::class, 'model_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function floodZone(): BelongsTo
    {
        return $this->belongsTo(FloodZone::class, 'flood_zone_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function predictionDetails(): HasMany
    {
        return $this->hasMany(PredictionDetail::class, 'prediction_id');
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(Recommendation::class, 'prediction_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function verify(int $userId): self
    {
        $this->status = 'verified';
        $this->verified_by = $userId;
        $this->verified_at = now();
        $this->save();

        return $this;
    }

    public function markAlerted(): self
    {
        $this->status = 'alerted';
        $this->save();

        return $this;
    }

    public function markExpired(): self
    {
        $this->status = 'expired';
        $this->save();

        return $this;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeAlerted($query)
    {
        return $query->where('status', 'alerted');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('issued_at', '>=', now()->subHours($hours));
    }

    public function scopeForZone($query, int $zoneId)
    {
        return $query->where('flood_zone_id', $zoneId);
    }
}
