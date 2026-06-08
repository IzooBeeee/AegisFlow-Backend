<?php

namespace App\Models;

use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * EvacuationRoute — Tuyến sơ tán
 */
class EvacuationRoute extends Model
{
    use HasFactory, HasTranslatedEnums;

    protected $fillable = [
        'name',
        'description',
        'start_node_id',
        'end_node_id',
        'geometry',
        'polyline',
        'distance_m',
        'estimated_time_seconds',
        'is_safe',
        'safety_rating',
        'risk_factors',
        'flood_zone_id',
        'shelter_id',
        'is_primary',
        'max_capacity',
        'current_usage',
        'status',
        'color',
    ];

    protected $casts = [
        'is_safe' => 'boolean',
        'safety_rating' => 'decimal:2',
        'risk_factors' => 'array',
        'is_primary' => 'boolean',
        'max_capacity' => 'integer',
        'current_usage' => 'integer',
        'distance_m' => 'integer',
        'estimated_time_seconds' => 'integer',
    ];

    protected static array $translatedEnums = [
        'status' => 'enums.evacuation_route_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function startNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'start_node_id');
    }

    public function endNode(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'end_node_id');
    }

    public function floodZone(): BelongsTo
    {
        return $this->belongsTo(FloodZone::class, 'flood_zone_id');
    }

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class, 'shelter_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(EvacuationRouteSegment::class, 'route_id')
            ->orderBy('sequence_order');
    }

    public function edges(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MapEdge::class, 'evacuation_route_segments', 'route_id', 'edge_id')
            ->withPivot('sequence_order', 'is_flood_prone', 'risk_level');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function getPolylineAttribute($value): ?array
    {
        if (! $value) {
            return null;
        }

        // Decode polyline thành mảng tọa độ
        return self::decodePolyline($value);
    }

    public function setPolylineAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['polyline'] = self::encodePolyline($value);
        } else {
            $this->attributes['polyline'] = $value;
        }
    }

    public static function encodePolyline(array $coordinates): string
    {
        $encoded = '';

        foreach ($coordinates as $point) {
            $encoded .= self::encodeNumber($point['lat']).','.self::encodeNumber($point['lng']).' ';
        }

        return trim($encoded);
    }

    public static function decodePolyline(string $encoded): array
    {
        $coordinates = [];
        $points = explode(' ', trim($encoded));
        $lat = 0;
        $lng = 0;

        foreach ($points as $point) {
            if (empty($point)) {
                continue;
            }

            [$dLat, $dLng] = explode(',', $point);
            $lat += self::decodeNumber($dLat);
            $lng += self::decodeNumber($dLng);

            $coordinates[] = ['lat' => $lat / 100000, 'lng' => $lng / 100000];
        }

        return $coordinates;
    }

    private static function encodeNumber(int $num): string
    {
        $sgnNum = $num < 0 ? ~($num << 1) : ($num << 1);
        $encoded = '';

        while ($sgnNum >= 0x20) {
            $encoded .= chr((0x20 | ($sgnNum & 0x1f)) + 63);
            $sgnNum >>= 5;
        }

        $encoded .= chr($sgnNum + 63);

        return $encoded;
    }

    private static function decodeNumber(string $encoded): int
    {
        $shift = 0;
        $result = 0;

        for ($i = 0; $i < strlen($encoded); $i++) {
            $ordVal = ord($encoded[$i]) - 63;
            $result |= ($ordVal & 0x1f) << $shift;
            $shift += 5;

            if ($ordVal < 0x20) {
                break;
            }
        }

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }

    /**
     * Cập nhật trạng thái khi ngập
     */
    public function markFlooded(): self
    {
        $this->status = 'flooded';
        $this->is_safe = false;
        $this->save();

        return $this;
    }

    /**
     * Cập nhật trạng thái an toàn
     */
    public function markSafe(): self
    {
        $this->status = 'active';
        $this->is_safe = true;
        $this->save();

        return $this;
    }

    public function getGeometryAttribute($value): ?array
    {
        if (! $value) {
            return null;
        }

        $result = DB::selectOne("
            SELECT ST_AsGeoJSON(geometry) as geojson FROM evacuation_routes WHERE id = ?
        ", [$this->id]);

        return $result ? json_decode($result->geojson) : null;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSafe($query)
    {
        return $query->where('is_safe', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeForZone($query, int $zoneId)
    {
        return $query->where('flood_zone_id', $zoneId);
    }
}
