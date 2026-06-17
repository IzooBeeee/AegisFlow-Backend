<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IncidentEvent — Timeline event cho sự cố
 */
class IncidentEvent extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'incident_id',
        'event_type',
        'description',
        'actor_id',
        'old_status',
        'new_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
