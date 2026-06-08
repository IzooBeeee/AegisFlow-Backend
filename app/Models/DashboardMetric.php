<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DashboardMetric — Metrics tổng hợp dashboard
 */
class DashboardMetric extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'metric_type',
        'district_id',
        'flood_zone_id',
        'period_start',
        'period_end',
        'period_type',
        'avg_value',
        'max_value',
        'min_value',
        'total_count',
        'active_count',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'avg_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'min_value' => 'decimal:4',
        'total_count' => 'integer',
        'active_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'period_type' => 'daily',
    ];
}
