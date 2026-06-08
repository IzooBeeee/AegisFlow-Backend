<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EvacuationRouteSegment — Segment của tuyến sơ tán
 */
class EvacuationRouteSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'edge_id',
        'sequence_order',
        'is_flood_prone',
        'risk_level',
    ];

    protected $casts = [
        'sequence_order' => 'integer',
        'is_flood_prone' => 'boolean',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(EvacuationRoute::class, 'route_id');
    }

    public function edge(): BelongsTo
    {
        return $this->belongsTo(MapEdge::class, 'edge_id');
    }
}
