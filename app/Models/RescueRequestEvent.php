<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RescueRequestEvent — Timeline yêu cầu cứu hộ
 */
class RescueRequestEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'event_type',
        'description',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(RescueRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
