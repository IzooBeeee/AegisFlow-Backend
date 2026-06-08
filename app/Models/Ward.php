<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ward — Phường/Xã
 */
class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'name',
        'code',
        'boundary',
        'population',
    ];

    protected $casts = [
        'population' => 'integer',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'ward_id');
    }

    public function rescueRequests(): HasMany
    {
        return $this->hasMany(RescueRequest::class, 'ward_id');
    }

    public function shelters(): HasMany
    {
        return $this->hasMany(Shelter::class, 'ward_id');
    }
}
