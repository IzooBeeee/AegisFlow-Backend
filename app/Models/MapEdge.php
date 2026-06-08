<?php

namespace App\Models;

use App\Enums\EvacuationRouteStatusEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * MapEdge — Đoạn đường / Cạnh đồ thị
 */
class MapEdge extends Model
{
    use HasFactory, SoftDeletes, HasTranslatedEnums;

    protected $fillable = [
        'external_id',
        'name',
        'source_node_id',
        'target_node_id',
        'geometry',
        'length_m',
        'lanes',
        'max_speed_kmh',
        'road_type',
        'direction',
        'surface_type',
        'has_sidewalk',
        'is_flood_prone',
        'current_density',
        'current_speed_kmh',
        'current_flow',
        'congestion_level',
        'status',
        'metrics_updated_at',
    ];

    protected $casts = [
        'length_m' => 'decimal:2',
        'lanes' => 'integer',
        'max_speed_kmh' => 'integer',
        'current_density' => 'decimal:4',
        'current_speed_kmh' => 'decimal:2',
        'current_flow' => 'decimal:2',
        'has_sidewalk' => 'boolean',
        'is_flood_prone' => 'boolean',
        'metrics_updated_at' => 'datetime',
    ];

    protected static array $translatedEnums = [
        'direction' => 'enums.edge_direction',
        'road_type' => 'enums.road_type',
        'congestion_level' => 'enums.congestion_level',
        'status' => 'enums.edge_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'target_node_id');
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'edge_id');
    }

    public function predictionEdges(): HasMany
    {
        return $this->hasMany(PredictionEdge::class, 'edge_id');
    }

    public function evacuationSegments(): HasMany
    {
        return $this->hasMany(EvacuationRouteSegment::class, 'edge_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Cập nhật metrics từ cảm biến
     */
    public function updateMetrics(float $density, float $speedKmh, ?float $flow = null): self
    {
        $this->current_density = $density;
        $this->current_speed_kmh = $speedKmh;
        $this->current_flow = $flow;
        $this->metrics_updated_at = now();

        $this->congestion_level = self::classifyCongestion($density, $speedKmh);
        $this->status = self::determineStatus($this->congestion_level);

        $this->save();

        return $this;
    }

    public static function classifyCongestion(float $density, float $speedKmh): string
    {
        return match (true) {
            $density > 0.8 && $speedKmh < 10 => 'gridlock',
            $density > 0.6 && $speedKmh < 20 => 'heavy',
            $density > 0.4 && $speedKmh < 30 => 'moderate',
            $density > 0.2 => 'light',
            default => 'none',
        };
    }

    public static function determineStatus(string $congestion): string
    {
        return match ($congestion) {
            'gridlock', 'heavy' => 'congested',
            default => 'normal',
        };
    }

    /**
     * Lấy GeoJSON
     */
    public function toGeoJson(): array
    {
        $geometry = DB::selectOne("
            SELECT ST_AsGeoJSON(geometry) as geojson FROM map_edges WHERE id = ?
        ", [$this->id]);

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'properties' => [
                'id' => $this->id,
                'name' => $this->name,
                'length_m' => $this->length_m,
                'congestion_level' => $this->congestion_level,
                'current_speed_kmh' => $this->current_speed_kmh,
                'status' => $this->status,
                'is_flood_prone' => $this->is_flood_prone,
            ],
            'geometry' => $geometry ? json_decode($geometry->geojson) : null,
        ];
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeCongested($query)
    {
        return $query->whereIn('status', ['congested', 'blocked']);
    }

    public function scopeFloodProne($query)
    {
        return $query->where('is_flood_prone', true);
    }

    public function scopeNormalStatus($query)
    {
        return $query->where('status', 'normal');
    }
}
