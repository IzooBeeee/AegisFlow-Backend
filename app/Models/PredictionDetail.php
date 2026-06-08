<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PredictionDetail — Chi tiết dự báo theo vùng
 */
class PredictionDetail extends Model
{
    use HasFactory;

    // No timestamps — only created_at for partition
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'prediction_details';

    protected $fillable = [
        'prediction_id',
        'entity_type',
        'entity_id',
        'predicted_value',
        'confidence',
        'probability',
        'severity',
        'risk_factors',
    ];

    protected $casts = [
        'predicted_value' => 'decimal:4',
        'confidence' => 'decimal:2',
        'probability' => 'decimal:4',
        'risk_factors' => 'array',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'prediction_id');
    }
}
