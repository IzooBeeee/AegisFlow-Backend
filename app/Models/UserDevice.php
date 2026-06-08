<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserDevice — Thiết bị FCM của người dùng
 *
 * @property int $id
 * @property int $user_id
 * @property string $fcm_token
 * @property string $device_type
 * @property string|null $device_name
 * @property string|null $device_model
 * @property string|null $os_version
 * @property bool $is_active
 * @property bool $notification_enabled
 * @property array|null $notification_settings
 * @property \Carbon\Carbon $last_used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'device_name',
        'device_model',
        'os_version',
        'is_active',
        'notification_enabled',
        'notification_settings',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notification_enabled' => 'boolean',
        'notification_settings' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope: active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: enabled notifications
     */
    public function scopeNotificationsEnabled($query)
    {
        return $query->where('is_active', true)->where('notification_enabled', true);
    }

    /**
     * Scope: by device type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Get active FCM tokens for user
     */
    public static function getActiveTokensForUser(int $userId): array
    {
        return static::where('user_id', $userId)
            ->active()
            ->notificationsEnabled()
            ->pluck('fcm_token')
            ->filter()
            ->toArray();
    }

    /**
     * Check if a token already exists
     */
    public static function tokenExists(string $token): bool
    {
        return static::where('fcm_token', $token)->exists();
    }

    /**
     * Find device by FCM token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('fcm_token', $token)->first();
    }

    /**
     * Update last used timestamp
     */
    public function touchLastUsed(): self
    {
        $this->last_used_at = now();
        $this->save();
        return $this;
    }

    /**
     * Disable all other devices of same type for user
     */
    public function deactivateOtherDevices(): void
    {
        if ($this->user_id && $this->device_type) {
            static::where('user_id', $this->user_id)
                ->where('device_type', $this->device_type)
                ->where('id', '!=', $this->id)
                ->update(['is_active' => false]);
        }
    }

    /**
     * Get default notification settings
     */
    public static function defaultNotificationSettings(): array
    {
        return [
            'flood_alerts' => true,
            'emergency_alerts' => true,
            'incident_updates' => true,
            'rescue_status' => true,
            'system_announcements' => true,
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ];
    }
}
