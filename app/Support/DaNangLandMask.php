<?php

namespace App\Support;

class DaNangLandMask
{
    private const BOUNDS = [
        'min_lng' => 108.02,
        'max_lng' => 108.29,
        'min_lat' => 15.82,
        'max_lat' => 16.16,
    ];

    private const LAND_POLYGONS = [
        // Mainland and western districts.
        [
            [108.020, 15.820],
            [108.225, 15.820],
            [108.235, 16.065],
            [108.205, 16.092],
            [108.165, 16.120],
            [108.055, 16.155],
            [108.020, 16.155],
        ],
        // Son Tra peninsula and the port area.
        [
            [108.205, 16.070],
            [108.235, 16.055],
            [108.285, 16.070],
            [108.292, 16.122],
            [108.255, 16.150],
            [108.215, 16.130],
            [108.195, 16.098],
        ],
        // Coastal Ngu Hanh Son strip.
        [
            [108.205, 15.850],
            [108.275, 15.850],
            [108.275, 16.070],
            [108.225, 16.070],
            [108.205, 15.980],
        ],
    ];

    private const WATER_POLYGONS = [
        // Da Nang Bay, between Lien Chieu/Thanh Khe and Son Tra.
        [
            [108.165, 16.082],
            [108.190, 16.086],
            [108.216, 16.098],
            [108.224, 16.109],
            [108.204, 16.119],
            [108.174, 16.107],
            [108.155, 16.091],
        ],
        // Lower bay water just north of Nguyen Tat Thanh coastline.
        [
            [108.168, 16.078],
            [108.190, 16.076],
            [108.210, 16.080],
            [108.224, 16.094],
            [108.216, 16.098],
            [108.190, 16.086],
            [108.165, 16.082],
        ],
    ];

    public static function isLikelyLand(float $lat, float $lng): bool
    {
        if (
            $lng < self::BOUNDS['min_lng']
            || $lng > self::BOUNDS['max_lng']
            || $lat < self::BOUNDS['min_lat']
            || $lat > self::BOUNDS['max_lat']
        ) {
            return false;
        }

        foreach (self::WATER_POLYGONS as $polygon) {
            if (self::pointInPolygon($lng, $lat, $polygon)) {
                return false;
            }
        }

        foreach (self::LAND_POLYGONS as $polygon) {
            if (self::pointInPolygon($lng, $lat, $polygon)) {
                return true;
            }
        }

        return false;
    }

    public static function featureIsLikelyLand(array $feature): bool
    {
        $geometry = $feature['geometry'] ?? null;
        if (is_object($geometry)) {
            $geometry = json_decode(json_encode($geometry), true);
        }

        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if ($type === 'Point' && is_array($coordinates)) {
            return self::coordinateIsLikelyLand($coordinates);
        }

        if (in_array($type, ['LineString', 'MultiPoint'], true) && is_array($coordinates)) {
            return collect($coordinates)->contains(fn ($coordinate) => self::coordinateIsLikelyLand($coordinate));
        }

        if (in_array($type, ['Polygon', 'MultiLineString'], true) && is_array($coordinates)) {
            return collect($coordinates)
                ->flatten(1)
                ->contains(fn ($coordinate) => self::coordinateIsLikelyLand($coordinate));
        }

        if ($type === 'MultiPolygon' && is_array($coordinates)) {
            return collect($coordinates)
                ->flatten(2)
                ->contains(fn ($coordinate) => self::coordinateIsLikelyLand($coordinate));
        }

        return false;
    }

    private static function coordinateIsLikelyLand(mixed $coordinate): bool
    {
        if (
            ! is_array($coordinate)
            || count($coordinate) < 2
            || ! is_numeric($coordinate[0])
            || ! is_numeric($coordinate[1])
        ) {
            return false;
        }

        return self::isLikelyLand((float) $coordinate[1], (float) $coordinate[0]);
    }

    private static function pointInPolygon(float $lng, float $lat, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$lngI, $latI] = $polygon[$i];
            [$lngJ, $latJ] = $polygon[$j];

            $intersects = (($latI > $lat) !== ($latJ > $lat))
                && ($lng < ($lngJ - $lngI) * ($lat - $latI) / (($latJ - $latI) ?: 1e-12) + $lngI);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
