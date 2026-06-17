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

        // Tạo một prediction record cho mỗi zone
        $predictions = $this->createPredictionsPerZone($response, $processingTimeMs, $inputData);

        foreach ($predictions as $prediction) {
            broadcast(new PredictionReceived($prediction))->toOthers();
            $generator->generate($prediction);
        }

        Log::info('CallAIPrediction: Predictions created', [
            'count' => count($predictions),
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
        $baseUrl = config('services.ai.url');
        $timeout = config('services.ai.timeout', 30);
        $headers = ['X-API-Key' => config('services.ai.key', ''), 'Content-Type' => 'application/json'];

        // ── Primary: batch flood prediction (existing endpoint) ───────────────
        $batchUrl = $baseUrl.config('services.ai.endpoints.predict_flood');
        $batchResponse = null;
        try {
            $resp = Http::timeout($timeout)->withHeaders($headers)->post($batchUrl, [
                'input_data' => $inputData,
                'horizon_minutes' => $this->horizonMinutes,
                'seasonality_enabled' => SystemSetting::getValue('ai.prediction.seasonality_enabled', true),
            ]);
            if ($resp->successful()) {
                $batchResponse = $resp->json();
            } else {
                Log::warning('CallAIPrediction: batch endpoint returned '.$resp->status());
            }
        } catch (\Throwable $e) {
            Log::warning('CallAIPrediction: batch endpoint failed — '.$e->getMessage());
        }

        // ── Secondary: smart AI analysis (forecast + spread) for primary zone ─
        $smartAnalysis = null;
        try {
            $primaryInput = $inputData[0] ?? [];
            $readings = collect($primaryInput['recent_readings'] ?? [])
                ->map(fn ($r) => [
                    'water_level_m' => (float) ($r['value'] ?? 0),
                    'rainfall_mm' => (float) ($primaryInput['weather']['rainfall_mm'] ?? 0),
                    'timestamp' => $r['recorded_at'] ?? null,
                ])
                ->values()
                ->all();

            $analyzePayload = [
                'water_level_m' => (float) ($primaryInput['current_water_level_m'] ?? 0),
                'rainfall_mm' => $primaryInput['weather']['rainfall_mm'] ?? null,
                'hours_rain' => 6,
                'tide_level' => 0.5,
                'historical_score' => 50.0,
                'readings' => $readings,
                'zone_id' => (string) ($primaryInput['zone_id'] ?? ''),
                'zone_name' => $primaryInput['zone_name'] ?? '',
                'soil_saturation' => 40.0,
                'include_forecast' => true,
                'include_spread' => false,   // skip spread for speed in scheduled runs
            ];

            $smartResp = Http::timeout(20)->withHeaders($headers)
                ->post($baseUrl.'/api/ai/analyze', $analyzePayload);

            if ($smartResp->successful()) {
                $smartAnalysis = $smartResp->json();
            }
        } catch (\Throwable $e) {
            Log::info('CallAIPrediction: smart analysis skipped — '.$e->getMessage());
        }

        if ($batchResponse) {
            // Merge smart analysis into batch response if available
            if ($smartAnalysis) {
                $batchResponse['smart_analysis'] = $smartAnalysis;
            }

            return $batchResponse;
        }

        // Fallback when batch also failed
        $fallback = $this->fallbackPredictionResponse($inputData);
        if ($smartAnalysis) {
            $fallback['smart_analysis'] = $smartAnalysis;
        }

        return $fallback;
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

    /**
     * Tạo một Prediction record per zone, kèm PredictionDetail.
     * Trả về mảng Prediction đã tạo.
     */
    protected function createPredictionsPerZone(array $aiResponse, int $processingTimeMs, array $inputData): array
    {
        $model = AIModel::production()->first();
        $smartAnalysis = $aiResponse['smart_analysis'] ?? null;

        // Index AI results theo zone_id để tra cứu nhanh
        $resultsByZone = collect($aiResponse['results'] ?? [])
            ->keyBy('zone_id')
            ->all();

        $created = [];

        foreach ($inputData as $zoneInput) {
            $zoneId = $zoneInput['zone_id'];
            $result = $resultsByZone[$zoneId] ?? [];

            $storedInputData = [
                'zone' => $zoneInput,
                'contributing_factors' => $result['contributing_factors'] ?? [],
                'timeseries_features' => $result['timeseries_features'] ?? [],
                'risk_factors' => $result['risk_factors'] ?? [],
                'risk_level' => $result['risk_level'] ?? null,
                'model_version' => $result['model_version'] ?? null,
                'prediction_method' => $result['prediction_method'] ?? null,
                // Smart AI analysis chỉ lưu cho zone đầu tiên (primary)
                'forecast' => ($zoneInput === $inputData[0]) ? ($smartAnalysis['forecast'] ?? null) : null,
                'weather' => ($zoneInput === $inputData[0]) ? ($smartAnalysis['weather'] ?? null) : null,
                'ai_summary' => ($zoneInput === $inputData[0]) ? ($smartAnalysis['summary'] ?? null) : null,
            ];

            $prediction = Prediction::create([
                'model_id' => $model?->id,
                'model_version' => $result['model_version'] ?? $model?->version,
                'prediction_type' => $result['type'] ?? 'flood_probability',
                'flood_zone_id' => $zoneId,
                'district_id' => FloodZone::find($zoneId)?->district_id,
                'incident_id' => $this->incidentId,
                'prediction_for' => now()->addMinutes($this->horizonMinutes),
                'horizon_minutes' => $this->horizonMinutes,
                'predicted_value' => $result['predicted_value'] ?? null,
                'confidence' => $result['confidence'] ?? null,
                'probability' => $this->normalizeProbability($result),
                'severity' => $this->normalizeSeverity($result),
                'input_data' => $storedInputData,
                'processing_time_ms' => $processingTimeMs,
                'status' => 'generated',
            ]);

            PredictionDetail::create([
                'prediction_id' => $prediction->id,
                'entity_type' => 'flood_zone',
                'entity_id' => $zoneId,
                'predicted_value' => $result['predicted_value'] ?? null,
                'confidence' => $result['confidence'] ?? null,
                'probability' => $this->normalizeProbability($result),
                'severity' => $this->normalizeSeverity($result),
                'risk_factors' => $result['risk_factors'] ?? [],
            ]);

            $created[] = $prediction;
        }

        return $created;
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
