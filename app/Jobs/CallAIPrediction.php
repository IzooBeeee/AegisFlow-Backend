<?php

namespace App\Jobs;

use App\Events\PredictionReceived;
use App\Models\AIModel;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\Prediction;
use App\Models\PredictionDetail;
use App\Models\Sensor;
use App\Models\SystemSetting;
use App\Models\WeatherData;
use App\Services\RecommendationGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CallAIPrediction implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public ?int $incidentId = null,
        public ?int $floodZoneId = null,
        public int $horizonMinutes = 60
    ) {}

    public function handle(RecommendationGenerator $generator): void
    {
        $startTime = microtime(true);

        // Tìm các cạnh/vùng cần dự báo
        $targets = $this->getPredictionTargets();

        if ($targets->isEmpty()) {
            Log::warning('CallAIPrediction: No targets found');

            return;
        }

        // Chuẩn bị dữ liệu đầu vào
        $inputData = $this->prepareInputData($targets);

        // Gọi AI service
        try {
            $response = $this->callAIService($inputData);
        } catch (\Exception $e) {
            Log::error('CallAIPrediction: AI service error', [
                'error' => $e->getMessage(),
                'incident_id' => $this->incidentId,
            ]);

            throw $e;
        }

        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Tạo prediction record
        $prediction = $this->createPrediction($response, $processingTimeMs, $inputData);

        // Tạo prediction details cho từng vùng
        $this->createPredictionDetails($prediction, $response);

        // Broadcast event
        broadcast(new PredictionReceived($prediction))->toOthers();

        // Sinh recommendations
        $generator->generate($prediction);

        Log::info('CallAIPrediction: Prediction created', [
            'prediction_id' => $prediction->id,
            'incident_id' => $this->incidentId,
        ]);
    }

    protected function getPredictionTargets(): Collection
    {
        // Ưu tiên theo flood zone
        if ($this->floodZoneId) {
            return FloodZone::active()->where('id', $this->floodZoneId)->get();
        }

        // Hoặc theo incident
        if ($this->incidentId) {
            $incident = Incident::with('floodZone')->find($this->incidentId);

            if ($incident?->floodZone) {
                return collect([$incident->floodZone]);
            }

            // Lấy tất cả flood zones nếu incident không có zone cụ thể
            return FloodZone::active()->get();
        }

        return FloodZone::active()->get();
    }

    protected function prepareInputData($targets): array
    {
        $data = [];

        foreach ($targets as $target) {
            // Lấy dữ liệu sensor readings gần đây
            $recentReadings = Sensor::where('flood_zone_id', $target->id)
                ->with(['latestReadings' => fn ($q) => $q->recent(24)])
                ->get()
                ->flatMap(fn ($s) => $s->latestReadings)
                ->values();

            $latestReading = $recentReadings->sortByDesc('recorded_at')->first();

            // Lấy weather data
            $weather = WeatherData::where('district_id', $target->district_id)
                ->orderByDesc('recorded_at')
                ->first();

            $data[] = [
                'zone_id' => $target->id,
                'zone_name' => $target->name,
                'alert_threshold_m' => $target->alert_threshold_m,
                'danger_threshold_m' => $target->danger_threshold_m,
                'current_water_level_m' => $latestReading?->value ?? $target->current_water_level_m,
                'recent_readings' => $recentReadings->take(10)->map(fn ($r) => [
                    'value' => $r->value,
                    'recorded_at' => $r->recorded_at?->toIso8601String(),
                ]),
                'weather' => $weather ? [
                    'rainfall_mm' => $weather->rainfall_mm,
                    'temperature_c' => $weather->temperature_c,
                    'humidity_pct' => $weather->humidity_pct,
                    'cloud_cover_pct' => $weather->cloud_cover_pct,
                    'recorded_at' => $weather->recorded_at?->toIso8601String(),
                ] : null,
            ];
        }

        return $data;
    }

    protected function callAIService(array $inputData): array
    {
        $url = config('services.ai.url').config('services.ai.endpoints.predict_flood');
        $timeout = config('services.ai.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-API-Key' => config('services.ai.key', ''),
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'input_data' => $inputData,
                    'horizon_minutes' => $this->horizonMinutes,
                    'seasonality_enabled' => SystemSetting::getValue('ai.prediction.seasonality_enabled', true),
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('CallAIPrediction: AI service unavailable, using local fallback', [
                'status' => $response->status(),
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CallAIPrediction: AI service request failed, using local fallback', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
        }

        return $this->fallbackPredictionResponse($inputData);
    }

    protected function fallbackPredictionResponse(array $inputData): array
    {
        $results = collect($inputData)->map(function (array $target) {
            $waterLevel = (float) ($target['current_water_level_m'] ?? 0);
            $alertThreshold = (float) ($target['alert_threshold_m'] ?? 1.5);
            $dangerThreshold = (float) ($target['danger_threshold_m'] ?? 3.0);
            $hasWeather = data_get($target, 'weather.rainfall_mm') !== null;
            $hasReadings = collect($target['recent_readings'] ?? [])->isNotEmpty();
            $rainfall = (float) data_get($target, 'weather.rainfall_mm', 0);
            $horizonFactor = max(0.25, min(2.5, $this->horizonMinutes / 60));
            $predictedValue = round($waterLevel + (($rainfall / 120) * $horizonFactor), 2);
            $thresholdSpan = max(0.1, $dangerThreshold - $alertThreshold);
            $waterRisk = $waterLevel >= $alertThreshold
                ? 0.35 + (min(1, max(0, ($waterLevel - $alertThreshold) / $thresholdSpan)) * 0.35)
                : min(0.3, max(0.08, $waterLevel / max(0.1, $alertThreshold) * 0.25));
            $rainRisk = min(0.35, $rainfall / 180);
            $probability = min(0.98, max(0.08, $waterRisk + $rainRisk));
            $confidence = 0.5
                + ($hasWeather ? 0.18 : 0)
                + ($hasReadings ? 0.14 : 0)
                + min(0.08, $rainfall / 600);

            return [
                'zone_id' => $target['zone_id'] ?? null,
                'type' => 'flood_risk',
                'predicted_value' => $predictedValue,
                'confidence' => round(min(0.92, max(0.42, $confidence)), 2),
                'probability' => round($probability, 4),
                'risk_factors' => array_values(array_filter([
                    $waterLevel >= $alertThreshold ? 'Mực nước hiện tại cao hơn ngưỡng cảnh báo' : null,
                    $rainfall >= 60 ? 'Mưa lớn kéo dài' : null,
                    $hasWeather && $rainfall <= 0 ? 'Thời tiết hiện tại không mưa' : null,
                    ! $hasWeather ? 'Thiếu dữ liệu thời tiết realtime' : null,
                    ! $hasReadings ? 'Thiếu dữ liệu cảm biến 24 giờ gần nhất' : null,
                    $this->horizonMinutes >= 120 ? 'Khung dự báo dài hạn' : null,
                ])),
            ];
        })->values()->all();

        return ['results' => $results];
    }

    protected function createPrediction(array $aiResponse, int $processingTimeMs, array $inputData): Prediction
    {
        $model = AIModel::production()->first();

        $primaryResult = $aiResponse['results'][0] ?? [];

        // Lưu cả input và output của AI để RecommendationGenerator dùng
        $storedInputData = [
            'zones'                => $inputData,
            'contributing_factors' => $primaryResult['contributing_factors'] ?? [],
            'timeseries_features'  => $primaryResult['timeseries_features']  ?? [],
            'risk_factors'         => $primaryResult['risk_factors']          ?? [],
            'risk_level'           => $primaryResult['risk_level']            ?? null,
            'model_version'        => $primaryResult['model_version']         ?? null,
            'prediction_method'    => $primaryResult['prediction_method']     ?? null,
        ];

        return Prediction::create([
            'model_id'        => $model?->id,
            'model_version'   => $primaryResult['model_version'] ?? $model?->version,
            'prediction_type' => $primaryResult['type'] ?? 'flood_probability',
            'flood_zone_id'   => $this->floodZoneId ?? ($primaryResult['zone_id'] ?? $inputData[0]['zone_id'] ?? null),
            'district_id'     => FloodZone::find($primaryResult['zone_id'] ?? $inputData[0]['zone_id'] ?? null)?->district_id,
            'incident_id'     => $this->incidentId,
            'prediction_for'  => now()->addMinutes($this->horizonMinutes),
            'horizon_minutes' => $this->horizonMinutes,
            'predicted_value' => $primaryResult['predicted_value'] ?? null,
            'confidence'      => $primaryResult['confidence'] ?? null,
            'probability'     => $this->normalizeProbability($primaryResult),
            'severity'        => $this->normalizeSeverity($primaryResult),
            'input_data'      => $storedInputData,
            'processing_time_ms' => $processingTimeMs,
            'status'          => 'generated',
        ]);
    }

    protected function createPredictionDetails(Prediction $prediction, array $aiResponse): void
    {
        foreach ($aiResponse['results'] ?? [] as $result) {
            PredictionDetail::create([
                'prediction_id' => $prediction->id,
                'entity_type' => 'flood_zone',
                'entity_id' => $result['zone_id'] ?? 0,
                'predicted_value' => $result['predicted_value'] ?? null,
                'confidence' => $result['confidence'] ?? null,
                'probability' => $this->normalizeProbability($result),
                'severity' => $this->normalizeSeverity($result),
                'risk_factors' => $result['risk_factors'] ?? [],
            ]);
        }
    }

    protected function normalizeProbability(array $result): float
    {
        if (isset($result['probability'])) {
            return (float) $result['probability'];
        }

        if (isset($result['risk_score'])) {
            return min(1, max(0, ((float) $result['risk_score']) / 100));
        }

        return 0.0;
    }

    protected function normalizeSeverity(array $result): string
    {
        $riskLevel = $result['risk_level'] ?? null;

        if (in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
            return $riskLevel;
        }

        return $this->classifySeverity($this->normalizeProbability($result));
    }

    protected function classifySeverity(float $probability): string
    {
        return match (true) {
            $probability >= 0.8 => 'critical',
            $probability >= 0.6 => 'high',
            $probability >= 0.4 => 'medium',
            default => 'low',
        };
    }
}
