<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SupplyAllocation — Phân bổ vật tư
 */
class SupplyAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'supply_id',
        'from_stock_id',
        'to_shelter_id',
        'to_team_id',
        'quantity',
        'status',
        'dispatched_at',
        'delivered_at',
        'delivered_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function request(): BelongsTo
    {
        return $this->belongsTo(RescueRequest::class, 'request_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(ReliefSupply::class, 'supply_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
