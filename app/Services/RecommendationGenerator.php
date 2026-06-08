<?php

namespace App\Services;

use App\Enums\AlertStatusEnum;
use App\Enums\AlertTypeEnum;
use App\Enums\RecommendationStatusEnum;
use App\Enums\RecommendationTypeEnum;
use App\Models\Alert;
use App\Models\FloodZone;
use App\Models\Prediction;
use App\Models\Recommendation;
use Illuminate\Support\Facades\DB;

/**
 * RecommendationGenerator — Tự động sinh đề xuất từ dự đoán
 */
class RecommendationGenerator
{
    /**
     * Sinh đề xuất từ prediction
     */
    public function generate(Prediction $prediction): array
    {
        $recommendations = [];

        $severity = $prediction->severity ?? 'low';
        $probability = $prediction->probability ?? 0;

        if ($probability >= 0.8 && in_array($severity, ['high', 'critical'])) {
            $recommendations = array_merge($recommendations, $this->generateCritical($prediction));
        } elseif ($probability >= 0.6 && $severity === 'high') {
            $recommendations = array_merge($recommendations, $this->generateHigh($prediction));
        } elseif ($probability >= 0.4) {
            $recommendations[] = $this->generateMedium($prediction);
        }

        return $recommendations;
    }

    /**
     * Đề xuất cho mức nghiêm trọng cao
     */
    protected function generateCritical(Prediction $prediction): array
    {
        return [
            // Tuyến ưu tiên
            $this->createRecommendation(
                $prediction,
                RecommendationTypeEnum::PRIORITY_ROUTE->value,
                'Kích hoạt tuyến ưu tiên — Dự báo ngập nghiêm trọng với xác suất '
                    .round($prediction->probability * 100).'%',
                [
                    'action' => 'activate_priority_route',
                    'urgency' => 'critical',
                    'probability' => $prediction->probability,
                ]
            ),

            // Cảnh báo khẩn cấp
            $this->createRecommendation(
                $prediction,
                RecommendationTypeEnum::ALERT->value,
                'Phát cảnh báo ngập cấp độ '
                    .($prediction->severity ?? 'high')
                    .' cho khu vực dự báo',
                [
                    'action' => 'broadcast_alert',
                    'severity' => $prediction->severity ?? 'high',
                    'auto_dispatch_rescue' => true,
                ]
            ),

            // Điều động cứu hộ
            $this->createRecommendation(
                $prediction,
                RecommendationTypeEnum::EVACUATION->value,
                'Chuẩn bị sơ tán dân cư trong vùng nguy hiểm',
                [
                    'action' => 'prepare_evacuation',
                    ' shelters_to_activate' => 'all_safe_shelters',
                ]
            ),
        ];
    }

    /**
     * Đề xuất cho mức cao
     */
    protected function generateHigh(Prediction $prediction): array
    {
        return [
            $this->createRecommendation(
                $prediction,
                RecommendationTypeEnum::REROUTE->value,
                'Đề xuất đổi tuyến giao thông tránh khu vực dự báo ngập',
                [
                    'action' => 'suggest_reroute',
                    'alternative_routes' => 'auto_calculate',
                ]
            ),

            $this->createRecommendation(
                $prediction,
                RecommendationTypeEnum::ALERT->value,
                'Cảnh báo người dân khu vực có nguy cơ ngập',
                [
                    'action' => 'notify_residents',
                    'radius_km' => 2,
                ]
            ),
        ];
    }

    /**
     * Đề xuất cho mức trung bình
     */
    protected function generateMedium(Prediction $prediction): Recommendation
    {
        return $this->createRecommendation(
            $prediction,
            RecommendationTypeEnum::REROUTE->value,
            'Cập nhật lộ trình di chuyển — nguy cơ ngập '
                .round($prediction->probability * 100).'%',
            [
                'action' => 'update_routes',
                'severity' => 'medium',
            ]
        );
    }

    /**
     * Tạo recommendation record
     */
    protected function createRecommendation(
        Prediction $prediction,
        string $type,
        string $description,
        array $details
    ): Recommendation {
        $recommendation = Recommendation::create([
            'prediction_id' => $prediction->id,
            'incident_id' => null,
            'type' => $type,
            'description' => $description,
            'details' => $details,
            'status' => RecommendationStatusEnum::PENDING->value,
        ]);

        if ($type === RecommendationTypeEnum::ALERT->value) {
            $this->publishAlert($prediction, $recommendation);
        }

        return $recommendation;
    }

    protected function publishAlert(Prediction $prediction, Recommendation $recommendation): ?Alert
    {
        $floodZone = $prediction->floodZone ?: FloodZone::find($prediction->flood_zone_id);
        $zoneId = $floodZone?->id;

        $recentQuery = Alert::active()
            ->where('source', 'ai')
            ->where('alert_type', AlertTypeEnum::FLOOD_WARNING->value)
            ->where('created_at', '>=', now()->subMinutes(30));

        if ($zoneId) {
            $recentQuery->whereJsonContains('affected_flood_zones', (string) $zoneId);
        } else {
            $recentQuery->where('related_prediction_id', $prediction->id);
        }

        if ($recentQuery->exists()) {
            return null;
        }

        $severity = in_array($prediction->severity, ['low', 'medium', 'high', 'critical'], true)
            ? $prediction->severity
            : ($prediction->probability >= 0.8 ? 'critical' : 'high');

        $area = $floodZone?->name ?: 'khu vực dự báo';
        $alert = Alert::create([
            'title' => '[AI Tự động] Cảnh báo ngập lụt',
            'description' => sprintf(
                '%s. Xác suất rủi ro %.0f%% cho %s trong %d phút tới.',
                $recommendation->description,
                ((float) $prediction->probability) * 100,
                $area,
                (int) $prediction->horizon_minutes
            ),
            'alert_type' => AlertTypeEnum::FLOOD_WARNING->value,
            'severity' => $severity,
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $floodZone?->district_id ? [(string) $floodZone->district_id] : [],
            'affected_wards' => [],
            'affected_flood_zones' => $zoneId ? [(string) $zoneId] : [],
            'radius_km' => 2,
            'effective_from' => now(),
            'effective_until' => now()->addHours(6),
            'source' => 'ai',
            'related_prediction_id' => $prediction->id,
            'related_incident_id' => $prediction->incident_id,
        ]);

        $centroid = $floodZone?->centroid_array;
        if ($centroid && DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE alerts SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [(float) $centroid['lng'], (float) $centroid['lat'], $alert->id]
            );
        }

        return $alert;
    }
}
