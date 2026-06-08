<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * SensorReading — Dữ liệu cảm biến (time-series)
 * NOTE: Bảng này sử dụng PARTITION BY RANGE (recorded_at) trong PostgreSQL
 */
class SensorReading extends Model
{
    use HasFactory;

    protected $table = 'sensor_readings';

    // Không có timestamps mặc định — dùng recorded_at làm partition key
    public const CREATED_AT = 'recorded_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'sensor_id',
        'recorded_at',
        'value',
        'raw_data',
        'is_anomaly',
        'quality_score',
        'source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_anomaly' => 'boolean',
        'quality_score' => 'decimal:2',
        'raw_data' => 'array',
        'recorded_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * Scope: readings trong khoảng thời gian
     */
    public function scopeInRange($query, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }

    /**
     * Scope: readings gần đây (vd: 24h)
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: readings bất thường
     */
    public function scopeAnomalies($query)
    {
        return $query->where('is_anomaly', true);
    }

    /**
     * Scope: readings từ sensor cụ thể
     */
    public function scopeFromSensor($query, int $sensorId)
    {
        return $query->where('sensor_id', $sensorId);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Kiểm tra có bất thường không dựa trên sensor
     */
    public function checkAnomaly(): bool
    {
        $sensor = $this->sensor;

        if (! $sensor) {
            return false;
        }

        if ($sensor->danger_threshold !== null && $this->value >= $sensor->danger_threshold) {
            return true;
        }

        if ($sensor->min_value !== null && $this->value < $sensor->min_value) {
            return true;
        }

        if ($sensor->max_value !== null && $this->value > $sensor->max_value) {
            return true;
        }

        return false;
    }

    /**
     * Lấy readings gần đây của sensor
     */
    public static function getRecentForSensor(int $sensorId, int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::fromSensor($sensorId)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * Tính trung bình trong khoảng thời gian
     */
    public static function getAverageForSensor(int $sensorId, int $hours = 24): ?float
    {
        $result = static::fromSensor($sensorId)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->selectRaw('AVG(value) as avg_value')
            ->first();

        return $result?->avg_value;
    }
}
