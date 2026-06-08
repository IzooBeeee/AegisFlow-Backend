<?php

namespace App\Models;

use App\Enums\FloodZoneRiskEnum;
use App\Enums\FloodZoneStatusEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * FloodZone — Vùng ngập lụt
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $geometry (PostGIS POLYGON)
 * @property string|null $centroid (PostGIS POINT)
 * @property int|null $district_id
 * @property array|null $ward_ids
 * @property string $risk_level (low|medium|high|critical)
 * @property float $base_water_level_m
 * @property float $current_water_level_m
 * @property float $alert_threshold_m
 * @property float $danger_threshold_m
 * @property float|null $area_km2
 * @property int|null $population_affected
 * @property string $status (monitoring|alert|danger|flooded|receded)
 * @property string $color
 * @property float $opacity
 * @property bool $is_active
 */
class FloodZone extends Model
{
    use HasFactory, SoftDeletes, HasTranslatedEnums;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'geometry',
        'centroid',
        'district_id',
        'ward_ids',
        'risk_level',
        'base_water_level_m',
        'current_water_level_m',
        'alert_threshold_m',
        'danger_threshold_m',
        'area_km2',
        'population_affected',
        'status',
        'color',
        'opacity',
        'is_active',
    ];

    protected $casts = [
        'ward_ids' => 'array',
        'base_water_level_m' => 'decimal:2',
        'current_water_level_m' => 'decimal:2',
        'alert_threshold_m' => 'decimal:2',
        'danger_threshold_m' => 'decimal:2',
        'area_km2' => 'decimal:2',
        'population_affected' => 'integer',
        'opacity' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static array $translatedEnums = [
        'risk_level' => 'enums.flood_zone_risk',
        'status' => 'enums.flood_zone_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'flood_zone_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'flood_zone_id');
    }

    public function evacuationRoutes(): HasMany
    {
        return $this->hasMany(EvacuationRoute::class, 'flood_zone_id');
    }

    public function mapNodes(): HasMany
    {
        return $this->hasMany(MapNode::class, 'flood_zone_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'flood_zone_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Kiểm tra ngưỡng cảnh báo
     */
    public function isAlertThreshold(float $waterLevel): bool
    {
        return $waterLevel >= $this->alert_threshold_m;
    }

    /**
     * Kiểm tra ngưỡng nguy hiểm
     */
    public function isDangerThreshold(float $waterLevel): bool
    {
        return $waterLevel >= $this->danger_threshold_m;
    }

    /**
     * Cập nhật mực nước hiện tại và trạng thái
     */
    public function updateWaterLevel(float $level): self
    {
        $this->current_water_level_m = $level;

        $this->status = match (true) {
            $level >= $this->danger_threshold_m => FloodZoneStatusEnum::FLOODED->value,
            $level >= $this->alert_threshold_m => FloodZoneStatusEnum::DANGER->value,
            $level >= ($this->alert_threshold_m * 0.7) => FloodZoneStatusEnum::ALERT->value,
            default => FloodZoneStatusEnum::MONITORING->value,
        };

        $this->save();

        // Broadcast event
        broadcast(new \App\Events\FloodZoneUpdated($this->fresh()))->toOthers();

        return $this;
    }

    /**
     * Lấy centroid dạng JSON
     */
    public function getCentroidArrayAttribute(): ?array
    {
        if (! $this->centroid) {
            return null;
        }

        $result = DB::selectOne("
            SELECT ST_X(centroid::geometry) as lng, ST_Y(centroid::geometry) as lat
            FROM flood_zones WHERE id = ?
        ", [$this->id]);

        return $result ? ['lat' => $result->lat, 'lng' => $result->lng] : null;
    }

    /**
     * Lấy GeoJSON của vùng ngập
     */
    public function toGeoJson(): array
    {
        $geometry = DB::selectOne("
            SELECT ST_AsGeoJSON(geometry) as geojson FROM flood_zones WHERE id = ?
        ", [$this->id]);

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'properties' => [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'risk_level' => $this->risk_level,
                'risk_level_label' => FloodZoneRiskEnum::from($this->risk_level)->label(),
                'status' => $this->status,
                'status_label' => FloodZoneStatusEnum::from($this->status)->label(),
                'current_water_level_m' => $this->current_water_level_m,
                'color' => $this->color,
                'opacity' => (float) $this->opacity,
            ],
            'geometry' => $geometry ? json_decode($geometry->geojson) : null,
        ];
    }

    /**
     * Scope: chỉ vùng đang active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: vùa đang có ngập
     */
    public function scopeFlooded($query)
    {
        return $query->whereIn('status', [FloodZoneStatusEnum::DANGER->value, FloodZoneStatusEnum::FLOODED->value]);
    }

    /**
     * Scope: theo mức rủi ro
     */
    public function scopeRiskLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }
}
