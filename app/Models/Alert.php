<?php

namespace App\Models;

use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Alert — Cảnh báo
 */
class Alert extends Model
{
    use HasFactory, HasTranslatedEnums;

    protected $fillable = [
        'alert_number',
        'title',
        'description',
        'alert_type',
        'severity',
        'status',
        'geometry',
        'affected_districts',
        'affected_wards',
        'affected_flood_zones',
        'radius_km',
        'effective_from',
        'effective_until',
        'issued_by',
        'resolved_by',
        'resolved_at',
        'source',
        'related_prediction_id',
        'related_incident_id',
    ];

    protected $casts = [
        'affected_districts' => 'array',
        'affected_wards' => 'array',
        'affected_flood_zones' => 'array',
        'radius_km' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static array $translatedEnums = [
        'alert_type' => 'enums.alert_type',
        'severity' => 'enums.severity',
        'status' => 'enums.alert_status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Alert $alert) {
            if (empty($alert->alert_number)) {
                $alert->alert_number = static::generateAlertNumber();
            }
        });

        static::created(function (Alert $alert) {
            event(new \App\Events\AlertCreated($alert));
        });
    }

    public static function generateAlertNumber(): string
    {
        $prefix = 'ALT';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        return "{$prefix}-{$date}-{$random}";
    }

    // ============================================================
    // Relationships
    // ============================================================

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function relatedPrediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'related_prediction_id');
    }

    public function relatedIncident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'related_incident_id');
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Illuminate\Notifications\DatabaseNotification::class, 'data->alert_id')
            ->where('type', \App\Notifications\AlertNotification::class);
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function resolve(int $userId): self
    {
        $this->status = 'resolved';
        $this->resolved_by = $userId;
        $this->resolved_at = now();
        $this->save();

        return $this;
    }

    public function expire(): self
    {
        $this->status = 'expired';
        $this->save();

        return $this;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'updated']);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeEffective($query)
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            });
    }
}
