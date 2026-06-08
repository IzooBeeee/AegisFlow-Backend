<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org/data/2.5';

    // Tọa độ trung tâm các quận Đà Nẵng
    private array $districtCoords = [
        1 => ['name' => 'Liên Chiểu',    'lat' => 16.0748, 'lon' => 108.1522],
        2 => ['name' => 'Cẩm Lệ',        'lat' => 15.9741, 'lon' => 108.2022],
        3 => ['name' => 'Hòa Vang',       'lat' => 15.9833, 'lon' => 108.1167],
        4 => ['name' => 'Hải Châu',       'lat' => 16.0544, 'lon' => 108.2022],
        5 => ['name' => 'Thanh Khê',      'lat' => 16.0678, 'lon' => 108.1878],
        6 => ['name' => 'Sơn Trà',        'lat' => 16.0678, 'lon' => 108.2378],
        7 => ['name' => 'Ngũ Hành Sơn',  'lat' => 15.9933, 'lon' => 108.2522],
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openweather.key', '');
    }

    /**
     * Lấy thời tiết hiện tại theo tọa độ
     */
    public function getCurrentWeather(float $lat, float $lon): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/weather", [
                'lat'   => $lat,
                'lon'   => $lon,
                'appid' => $this->apiKey,
                'units' => 'metric',
                'lang'  => 'vi',
            ]);

            if ($response->failed()) {
                Log::warning('[OpenWeather] API failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();

            return [
                'temperature_c'  => $data['main']['temp'] ?? null,
                'humidity_pct'   => $data['main']['humidity'] ?? null,
                'wind_speed_kmh' => isset($data['wind']['speed']) ? round($data['wind']['speed'] * 3.6, 1) : null,
                'wind_direction' => $data['wind']['deg'] ?? null,
                'rainfall_mm'    => $data['rain']['1h'] ?? $data['rain']['3h'] ?? 0,
                'pressure_hpa'   => $data['main']['pressure'] ?? null,
                'cloud_cover_pct'=> $data['clouds']['all'] ?? null,
                'description'    => $data['weather'][0]['description'] ?? null,
                'recorded_at'    => now(),
            ];
        } catch (\Exception $e) {
            Log::error('[OpenWeather] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getDistrictCoords(): array
    {
        return $this->districtCoords;
    }
}
