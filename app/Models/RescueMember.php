<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * RescueMember — Thành viên đội cứu hộ (pivot user ↔ team)
 */
class RescueMember extends Pivot
{
    use HasFactory;

    protected $table = 'rescue_members';

    public $timestamps = true;
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'team_id',
        'role',
        'badge_number',
        'certifications',
        'status',
        'is_available',
    ];

    protected $casts = [
        'certifications' => 'array',
        'is_available' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'team_id');
    }
}
