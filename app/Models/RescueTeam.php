<?php

namespace App\Models;

use App\Enums\RescueTeamStatusEnum;
use App\Enums\RescueTeamTypeEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * RescueTeam — Đội cứu hộ
 */
class RescueTeam extends Model
{
    use HasFactory, HasTranslatedEnums;

    protected $fillable = [
        'name',
        'code',
        'team_type',
        'district_id',
        'specializations',
        'vehicle_count',
        'personnel_count',
        'equipment',
        'phone',
        'status',
        'current_latitude',
        'current_longitude',
        'current_location',
        'current_node_id',
        'heading_to_incident_id',
        'last_location_update',
    ];

    protected $casts = [
        'specializations' => 'array',
        'equipment' => 'array',
        'vehicle_count' => 'integer',
        'personnel_count' => 'integer',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'last_location_update' => 'datetime',
    ];

    protected static array $translatedEnums = [
        'team_type' => 'enums.rescue_team_type',
        'status' => 'enums.rescue_team_status',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RescueMember::class, 'team_id');
    }

    public function headingToIncident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'heading_to_incident_id');
    }

    public function assignedRequests(): HasMany
    {
        return $this->hasMany(RescueRequest::class, 'assigned_team_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Cập nhật vị trí GPS
     */
    public function updateLocation(float $lat, float $lng): self
    {
        $this->current_latitude = $lat;
        $this->current_longitude = $lng;
        $this->current_location = DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)");
        $this->last_location_update = now();
        $this->save();

        return $this;
    }

    /**
     * Kiểm tra đội có sẵn sàng không
     */
    public function isAvailable(): bool
    {
        return $this->status === RescueTeamStatusEnum::AVAILABLE->value
            && $this->personnel_count > 0;
    }

    /**
     * Lấy vị trí hiện tại dạng mảng
     */
    public function getLocationAttribute(): ?array
    {
        return [
            'lat' => $this->current_latitude,
            'lng' => $this->current_longitude,
        ];
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeAvailable($query)
    {
        return $query->where('status', RescueTeamStatusEnum::AVAILABLE->value);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('team_type', $type);
    }

    public function scopeByDistrict($query, int $districtId)
    {
        return $query->where('district_id', $districtId);
    }
}
