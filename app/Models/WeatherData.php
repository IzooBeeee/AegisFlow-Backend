<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WeatherData — Dữ liệu thời tiết
 */
class WeatherData extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'district_id',
        'recorded_at',
        'temperature_c',
        'humidity_pct',
        'wind_speed_kmh',
        'wind_direction',
        'rainfall_mm',
        'pressure_hpa',
        'visibility_km',
        'cloud_cover_pct',
        'uv_index',
        'source',
        'forecast_date',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature_c' => 'decimal:2',
        'humidity_pct' => 'decimal:2',
        'wind_speed_kmh' => 'decimal:2',
        'rainfall_mm' => 'decimal:2',
        'pressure_hpa' => 'decimal:2',
        'visibility_km' => 'decimal:2',
        'cloud_cover_pct' => 'decimal:2',
        'uv_index' => 'decimal:2',
        'forecast_date' => 'date',
    ];

    protected $attributes = [
        'source' => 'openweather',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
