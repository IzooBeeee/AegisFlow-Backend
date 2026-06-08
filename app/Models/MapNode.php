<?php

namespace App\Models;

use App\Enums\MapNodeTypeEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * MapNode — Nút giao thông
 */
class MapNode extends Model
{
    use HasFactory, SoftDeletes, HasTranslatedEnums;

    protected $fillable = [
        'external_id',
        'name',
        'type',
        'geometry',
        'district_id',
        'ward_id',
        'elevation_m',
        'flood_zone_id',
        'is_safe',
        'status',
        'metadata',
    ];

    protected $casts = [
        'elevation_m' => 'decimal:2',
        'is_safe' => 'boolean',
        'metadata' => 'array',
    ];

    protected static array $translatedEnums = [
        'type' => 'enums.map_node_type',
        'status' => 'enums.node_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function floodZone(): BelongsTo
    {
        return $this->belongsTo(FloodZone::class, 'flood_zone_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(MapEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(MapEdge::class, 'target_node_id');
    }

    public function evacuationRoutesStart(): HasMany
    {
        return $this->hasMany(EvacuationRoute::class, 'start_node_id');
    }

    public function evacuationRoutesEnd(): HasMany
    {
        return $this->hasMany(EvacuationRoute::class, 'end_node_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function getLocationAttribute(): ?array
    {
        $result = DB::selectOne("
            SELECT ST_X(geometry::geometry) as lng, ST_Y(geometry::geometry) as lat
            FROM map_nodes WHERE id = ?
        ", [$this->id]);

        return $result ? ['lat' => $result->lat, 'lng' => $result->lng] : null;
    }

    public function getConnectedNodes(): \Illuminate\Support\Collection
    {
        $outgoing = $this->outgoingEdges()->pluck('target_node_id');
        $incoming = $this->incomingEdges()->pluck('source_node_id');

        return MapNode::whereIn('id', $outgoing->merge($incoming))->get();
    }

    public function scopeSafe($query)
    {
        return $query->where('is_safe', true);
    }
}
