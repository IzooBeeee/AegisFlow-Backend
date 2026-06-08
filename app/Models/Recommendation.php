<?php

namespace App\Models;

use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recommendation — Đề xuất hành động từ AI
 */
class Recommendation extends Model
{
    use HasFactory, SoftDeletes, HasTranslatedEnums;

    protected $fillable = [
        'prediction_id',
        'incident_id',
        'type',
        'description',
        'details',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'executed_at',
    ];

    protected $casts = [
        'details'     => 'array',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    protected static array $translatedEnums = [
        'type'   => 'enums.recommendation_type',
        'status' => 'enums.recommendation_status',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'prediction_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
