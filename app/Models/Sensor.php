<?php

namespace App\Models;

use App\Enums\SensorStatusEnum;
use App\Enums\SensorTypeEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Sensor — Cảm biến môi trường
 *
 * @property int $id
 * @property string $external_id
 * @property string $name
 * @property string $type (water_level|rainfall|camera|wind|temperature|humidity|combined)
 * @property string|null $model
 * @property string $geometry (PostGIS POINT)
 * @property int|null $flood_zone_id
 * @property int|null $district_id
 * @property string $status (online|offline|maintenance|error)
 * @property float|null $min_value
 * @property float|null $max_value
 * @property string $unit
 * @property int $reading_interval_seconds
 * @property float|null $alert_threshold
 * @property float|null $danger_threshold
 * @property \Carbon\Carbon|null $last_reading_at
 * @property float|null $last_value
 * @property array|null $metadata
 * @property bool $is_active
 */
class Sensor extends Model
{
    use HasFactory, SoftDeletes, HasTranslatedEnums;

    protected $fillable = [
        'external_id',
        'name',
        'type',
        'model',
        'geometry',
        'flood_zone_id',
        'district_id',
        'status',
        'min_value',
        'max_value',
        'unit',
        'reading_interval_seconds',
        'alert_threshold',
        'danger_threshold',
        'last_reading_at',
        'last_value',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'alert_threshold' => 'decimal:2',
        'danger_threshold' => 'decimal:2',
        'last_value' => 'decimal:2',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_reading_at' => 'datetime',
        'reading_interval_seconds' => 'integer',
    ];

    protected static array $translatedEnums = [
        'type' => 'enums.sensor_type',
        'status' => 'enums.sensor_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function floodZone(): BelongsTo
    {
        return $this->belongsTo(FloodZone::class, 'flood_zone_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class, 'sensor_id');
    }

    public function latestReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class, 'sensor_id')
            ->latest('recorded_at')
            ->limit(100);
    }

    public function edge(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MapEdge::class, 'edge_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Kiểm tra cảm biến có online không
     */
    public function isOnline(): bool
    {
        return $this->status === SensorStatusEnum::ONLINE->value;
    }

    /**
     * Kiểm tra giá trị có bất thường không
     */
    public function isAnomaly(float $value): bool
    {
        if ($this->min_value !== null && $value < $this->min_value) {
            return true;
        }

        if ($this->max_value !== null && $value > $this->max_value) {
            return true;
        }

        if ($this->danger_threshold !== null && $value >= $this->danger_threshold) {
            return true;
        }

        return false;
    }

    /**
     * Kiểm tra có vượt ngưỡng cảnh báo không
     */
    public function isAlert(float $value): bool
    {
        return $this->alert_threshold !== null && $value >= $this->alert_threshold;
    }

    /**
     * Lấy vị trí tọa độ
     */
    public function getLocationAttribute(): ?array
    {
        $result = DB::selectOne("
            SELECT ST_X(geometry::geometry) as lng, ST_Y(geometry::geometry) as lat
            FROM sensors WHERE id = ?
        ", [$this->id]);

        return $result ? ['lat' => $result->lat, 'lng' => $result->lng] : null;
    }

    /**
     * Cập nhật giá trị mới nhất
     */
    public function updateLastReading(float $value, \DateTimeInterface $recordedAt): self
    {
        $this->last_value = $value;
        $this->last_reading_at = $recordedAt;
        $this->status = SensorStatusEnum::ONLINE->value;
        $this->save();

        return $this;
    }

    /**
     * Đánh dấu offline nếu quá thời gian không đọc
     */
    public function checkOfflineStatus(): self
    {
        $timeout = config('services.flood.sensor_offline_timeout', 600);

        if ($this->last_reading_at &&
            $this->last_reading_at->diffInSeconds(now()) > $timeout &&
            $this->status !== SensorStatusEnum::MAINTENANCE->value
        ) {
            $this->status = SensorStatusEnum::OFFLINE->value;
            $this->save();
        }

        return $this;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', SensorStatusEnum::ONLINE->value);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeZone($query, int $zoneId)
    {
        return $query->where('flood_zone_id', $zoneId);
    }
}
