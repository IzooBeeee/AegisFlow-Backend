<?php

namespace App\Models;

use App\Enums\UrgencyEnum;
use App\Traits\HasTranslatedEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RescueRequest — Yêu cầu cứu hộ
 */
class RescueRequest extends Model
{
    use HasFactory, HasTranslatedEnums, SoftDeletes;

    protected $fillable = [
        'request_number',
        'caller_name',
        'caller_phone',
        'geometry',
        'address',
        'district_id',
        'ward_id',
        'incident_id',
        'urgency',
        'category',
        'people_count',
        'vulnerable_groups',
        'description',
        'photo_urls',
        'water_level_m',
        'accessibility_notes',
        'status',
        'priority_score',
        'assigned_team_id',
        'assigned_at',
        'eta_minutes',
        'completed_at',
        'cancellation_reason',
        'rating',
        'feedback',
        'reported_by',
    ];

    protected $casts = [
        'photo_urls' => 'array',
        'vulnerable_groups' => 'array',
        'people_count' => 'integer',
        'priority_score' => 'decimal:2',
        'water_level_m' => 'decimal:2',
        'eta_minutes' => 'integer',
        'rating' => 'integer',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static array $translatedEnums = [
        'urgency' => 'enums.urgency',
        'category' => 'enums.rescue_category',
        'status' => 'enums.rescue_request_status',
    ];

    protected static function booted(): void
    {
        static::creating(function (RescueRequest $req) {
            if (empty($req->request_number)) {
                $req->request_number = static::generateRequestNumber();
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        $prefix = 'RRQ';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

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

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(RescueTeam::class, 'assigned_team_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RescueRequestEvent::class, 'request_id')
            ->orderBy('created_at', 'asc');
    }

    public function supplyAllocations(): HasMany
    {
        return $this->hasMany(SupplyAllocation::class, 'request_id');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function getLocationAttribute(): ?array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $result = DB::selectOne('
                        SELECT ST_X(geometry::geometry) as lng, ST_Y(geometry::geometry) as lat
                        FROM rescue_requests WHERE id = ?
                    ', [$this->id]);

            return $result ? ['lat' => $result->lat, 'lng' => $result->lng] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Tính điểm ưu tiên
     */
    public function calculatePriorityScore(): float
    {
        $score = 40;

        // 1. Mức độ khẩn (30 điểm)
        $urgency = UrgencyEnum::from($this->urgency);
        $score += match ($this->urgency) {
            UrgencyEnum::CRITICAL->value => 30,
            UrgencyEnum::HIGH->value => 20,
            UrgencyEnum::MEDIUM->value => 10,
            default => 0,
        };

        // 2. Nhóm yếu thế (25 điểm)
        $vulnerableGroups = $this->vulnerable_groups ?? [];
        $score += in_array('children', $vulnerableGroups) ? 10 : 0;
        $score += in_array('pregnant', $vulnerableGroups) ? 7 : 0;
        $score += in_array('elderly', $vulnerableGroups) ? 8 : 0;
        $score += in_array('disabled', $vulnerableGroups) ? 5 : 0;

        // 3. Số người (15 điểm)
        $score += match (true) {
            $this->people_count >= 10 => 15,
            $this->people_count >= 5 => 10,
            $this->people_count >= 2 => 5,
            default => 0,
        };

        // 4. Mực nước (15 điểm)
        if ($this->water_level_m) {
            $score += match (true) {
                $this->water_level_m >= 3.0 => 15,
                $this->water_level_m >= 1.5 => 10,
                $this->water_level_m >= 0.5 => 5,
                default => 0,
            };
        }

        // 5. Thời gian chờ (10 điểm)
        $waitingMinutes = $this->created_at->diffInMinutes(now());
        $score += match (true) {
            $waitingMinutes >= 120 => 10,
            $waitingMinutes >= 60 => 7,
            $waitingMinutes >= 30 => 4,
            $waitingMinutes >= 15 => 2,
            default => 0,
        };

        // 6. Kết hợp incident (5 điểm)
        if ($this->incident_id) {
            $score += 5;
        }

        $this->priority_score = min(100, $score);
        $this->saveQuietly();

        return $this->priority_score;
    }

    /**
     * Gán đội cứu hộ
     */
    public function assignTeam(RescueTeam $team): self
    {
        $this->assigned_team_id = $team->id;
        $this->assigned_at = now();
        $this->status = 'assigned';
        $this->save();

        $team->status = 'dispatched';
        $team->heading_to_incident_id = $this->incident_id;
        $team->saveQuietly();

        return $this;
    }

    /**
     * Hoàn thành yêu cầu
     */
    public function complete(): self
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        if ($this->assignedTeam) {
            $this->assignedTeam->status = 'available';
            $this->assignedTeam->heading_to_incident_id = null;
            $this->assignedTeam->saveQuietly();
        }

        return $this;
    }

    /**
     * Hủy yêu cầu
     */
    public function cancel(string $reason): self
    {
        $this->status = 'cancelled';
        $this->cancellation_reason = $reason;
        $this->save();

        return $this;
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeCritical($query)
    {
        return $query->where('urgency', UrgencyEnum::CRITICAL->value);
    }

    public function scopeByDistrict($query, int $districtId)
    {
        return $query->where('district_id', $districtId);
    }
}
