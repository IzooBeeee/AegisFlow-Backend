<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReliefSupply — Vật tư cứu hộ
 */
class ReliefSupply extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'unit',
        'quantity',
        'min_stock_level',
        'expiry_date',
        'storage_location',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'min_stock_level' => 'decimal:2',
        'expiry_date' => 'date',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupplyStock::class, 'supply_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function isLowStock(): bool
    {
        return $this->min_stock_level !== null && $this->quantity <= $this->min_stock_level;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereNotNull('min_stock_level')
            ->whereColumn('quantity', '<=', 'min_stock_level');
    }
}
