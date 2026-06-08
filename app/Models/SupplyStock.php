<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SupplyStock — Tồn kho vật tư tại điểm trú ẩn / đội cứu hộ
 */
class SupplyStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_id',
        'stockable_type',
        'stockable_id',
        'quantity',
        'reserved_quantity',
        'last_restocked_at',
        'expiry_tracking',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'expiry_tracking' => 'boolean',
        'last_restocked_at' => 'datetime',
    ];

    // ============================================================
    // Computed
    // ============================================================

    public function getAvailableQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    // ============================================================
    // Relationships
    // ============================================================

    public function supply(): BelongsTo
    {
        return $this->belongsTo(ReliefSupply::class, 'supply_id');
    }

    public function stockable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
