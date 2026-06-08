<?php

namespace App\Models;

use App\Enums\ShelterStatusEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Shelter — Điểm trú ẩn
 */
class Shelter extends Model
{
    use HasFactory, HasTranslatedEnums;

    protected $fillable = [
        'name',
        'code',
        'geometry',
        'address',
        'district_id',
        'ward_id',
        'shelter_type',
        'capacity',
        'current_occupancy',
        'facilities',
        'status',
        'accessibility',
        'is_flood_safe',
        'flood_depth_tolerance_m',
        'contact_phone',
        'contact_name',
        'opening_hours',
        'metadata',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'current_occupancy' => 'integer',
        'facilities' => 'array',
        'flood_depth_tolerance_m' => 'decimal:2',
        'is_flood_safe' => 'boolean',
    ];

    protected static array $translatedEnums = [
        'status' => 'enums.shelter_status',
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

    public function supplyStocks(): HasMany
    {
        return $this->hasMany(SupplyStock::class, 'stockable_id')
            ->where('stockable_type', 'Shelter');
    }

    public function evacuationRoutes(): HasMany
    {
        return $this->hasMany(EvacuationRoute::class, 'shelter_id');
    }

    // ============================================================
    // Computed
    // ============================================================

    public function getAvailableBedsAttribute(): int
    {
        return max(0, $this->capacity - $this->current_occupancy);
    }

    public function getOccupancyPercentAttribute(): float
    {
        if ($this->capacity === 0) {
            return 0;
        }

        return round(($this->current_occupancy / $this->capacity) * 100, 1);
    }

    public function getLocationAttribute(): ?array
    {
        $result = DB::selectOne('
            SELECT ST_X(geometry::geometry) as lng, ST_Y(geometry::geometry) as lat
            FROM shelters WHERE id = ?
        ', [$this->id]);

        if ($result && $result->lat !== null && $result->lng !== null) {
            return ['lat' => (float) $result->lat, 'lng' => (float) $result->lng];
        }

        $fallbacks = [
            'SHELTER-001' => ['lat' => 16.0712, 'lng' => 108.1498],
            'SHELTER-002' => ['lat' => 16.0039, 'lng' => 108.2062],
            'SHELTER-003' => ['lat' => 16.0134, 'lng' => 108.1921],
            'SHELTER-004' => ['lat' => 15.9756, 'lng' => 108.1234],
        ];

        return $fallbacks[$this->code] ?? null;
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function hasCapacity(int $count): bool
    {
        return $this->available_beds >= $count;
    }

    public function addOccupants(int $count): self
    {
        $this->current_occupancy += $count;

        if ($this->current_occupancy >= $this->capacity * 0.9) {
            $this->status = ShelterStatusEnum::FULL->value;
        }

        $this->save();

        return $this;
    }

    public function removeOccupants(int $count): self
    {
        $this->current_occupancy = max(0, $this->current_occupancy - $count);

        if ($this->current_occupancy < $this->capacity * 0.9) {
            $this->status = ShelterStatusEnum::OPEN->value;
        }

        $this->save();

        return $this;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeOpen($query)
    {
        return $query->where('status', ShelterStatusEnum::OPEN->value);
    }

    public function scopeHasCapacity($query)
    {
        return $query->whereRaw('(capacity - current_occupancy) > 0');
    }

    public function scopeFloodSafe($query)
    {
        return $query->where('is_flood_safe', true);
    }

    public function scopeByDistrict($query, int $districtId)
    {
        return $query->where('district_id', $districtId);
    }
}
