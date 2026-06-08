<?php

namespace App\Traits;

use Illuminate\Support\Facades\Lang;

/**
 * HasTranslatedEnums — Hỗ trợ i18n cho enum fields trong model
 *
 * Sử dụng:
 *   1. Khai báo static $translatedEnums = ['field_name' => 'group.key'] trong model
 *   2. Gọi $model->translated('field_name') → string (label dịch)
 *   3. Gọi $model->enumOptions('field_name') → array (tất cả options)
 *   4. Gọi $model->enumGroup('field_name') → string (translation key)
 *
 * Lang file: resources/lang/{locale}/enums.php
 */
trait HasTranslatedEnums
{
    /**
     * Lấy label dịch cho giá trị enum
     *
     * @param  string  $field
     * @return string
     */
    public function translated(string $field): string
    {
        $value = $this->{$field};
        $map = property_exists(static::class, 'translatedEnums') ? static::$translatedEnums : [];

        if (! isset($map[$field])) {
            return $value;
        }

        $key = $map[$field].'.'.$value;

        return Lang::has($key) ? __($key) : $value;
    }

    /**
     * Lấy tất cả options cho một enum field
     *
     * @param  string  $field
     * @return array
     */
    public function enumOptions(string $field): array
    {
        $options = self::getEnumCases($field);

        return array_map(function ($option) use ($field) {
            $map = property_exists(static::class, 'translatedEnums') ? static::$translatedEnums : [];
            $key = $map[$field].'.'.$option;

            return [
                'value' => $option,
                'label' => Lang::has($key) ? __($key) : $option,
            ];
        }, $options);
    }

    /**
     * Lấy translation group key
     */
    public function enumGroup(string $field): ?string
    {
        $map = property_exists(static::class, 'translatedEnums') ? static::$translatedEnums : [];
        return $map[$field] ?? null;
    }

    /**
     * Lấy tất cả giá trị enum từ BackedEnum hoặc raw array
     *
     * @param  string  $field
     * @return array
     */
    protected static function getEnumCases(string $field): array
    {
        $fillable = (new static())->getFillable();

        // Nếu field không phải enum, trả về rỗng
        if (! in_array($field, $fillable) && ! property_exists(new static(), $field)) {
            return [];
        }

        // Thử lấy từ enum class (nếu có)
        $enumClass = static::getEnumClass($field);

        if ($enumClass && enum_exists($enumClass)) {
            return array_column($enumClass::cases(), 'value');
        }

        // Fallback: lấy từ translation file
        $map = property_exists(static::class, 'translatedEnums') ? static::$translatedEnums : [];
        if (isset($map[$field])) {
            $group = $map[$field];
            $translations = __($group);

            if (is_array($translations)) {
                return array_keys($translations);
            }
        }

        return [];
    }

    /**
     * Tìm enum class cho field (đặt tên theo convention)
     *
     * @param  string  $field
     * @return string|null
     */
    protected static function getEnumClass(string $field): ?string
    {
        $modelClass = static::class;
        $parts = explode('\\', $modelClass);
        $modelName = end($parts);
        $enumName = ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));

        $possiblePaths = [
            $modelClass::enumNamespace().'\\'.$modelName.ucfirst($field).'Enum',
            $modelClass::enumNamespace().'\\'.ucfirst($field).'Enum',
            'App\\Enums\\'.$modelName.ucfirst($field).'Enum',
            'App\\Enums\\'.ucfirst($field).'Enum',
            'App\\Enums\\'.$modelName.'StatusEnum',
            'App\\Enums\\StatusEnum',
        ];

        foreach ($possiblePaths as $path) {
            if (class_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Override trong model con nếu cần
     */
    protected static function enumNamespace(): string
    {
        return 'App\\Enums';
    }
}
