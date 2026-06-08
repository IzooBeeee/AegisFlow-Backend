<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * District — Quận/Huyện
 */
class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'boundary',
        'population',
        'area_km2',
        'risk_level',
    ];

    protected $casts = [
        'population' => 'integer',
        'area_km2' => 'decimal:2',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class, 'district_id');
    }

    public function floodZones(): HasMany
    {
        return $this->hasMany(FloodZone::class, 'district_id');
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'district_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'district_id');
    }

    public function rescueRequests(): HasMany
    {
        return $this->hasMany(RescueRequest::class, 'district_id');
    }

    public function shelters(): HasMany
    {
        return $this->hasMany(Shelter::class, 'district_id');
    }

    public function rescueTeams(): HasMany
    {
        return $this->hasMany(RescueTeam::class, 'district_id');
    }

    public function mapNodes(): HasMany
    {
        return $this->hasMany(MapNode::class, 'district_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'district_id');
    }

    public function weatherData(): HasMany
    {
        return $this->hasMany(WeatherData::class, 'district_id');
    }
}
