<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group_name',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json' => json_decode($setting->value ?: 'null', true),
            default => $setting->value,
        };
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $type = 'string',
        ?string $group = null,
        ?string $description = null,
        bool $isPublic = false
    ): self {
        $storedValue = $type === 'json' ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group_name' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );
    }
}
