<?php

namespace App\Services;

use App\Enums\AlertStatusEnum;
use App\Enums\AlertTypeEnum;
use App\Enums\RecommendationStatusEnum;
use App\Enums\RecommendationTypeEnum;
use App\Models\Alert;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\Prediction;
use App\Models\Recommendation;
use App\Models\RescueTeam;
use Illuminate\Support\Facades\DB;

/**
 * RecommendationGenerator — Sinh đề xuất thông minh dựa trên nhiều nguồn dữ liệu:
 *  - Prediction (severity, probability, predicted_value, input_data, horizon_minutes)
 *  - input_data.contributing_factors (water_level_trend, rain_6h, soil_saturation)
 *  - FloodZone (alert_threshold_m, danger_threshold_m, district)
 *  - Incident đang hoạt động trong cùng vùng
 *  - RescueTeam trạng thái available
 *  - Các Prediction cùng khu vực gần đây (escalation)
 */
class RecommendationGenerator
{
    /**
     * Sinh đề xuất từ một Prediction.
     * Nạp đủ relationships trước khi phân tích.
     */
    public function generate(Prediction $prediction): array
    {
        // Nạp context đầy đủ
        $prediction->loadMissing(['floodZone', 'floodZone.district']);

        $ctx = $this->buildContext($prediction);

        // Tránh tạo lại nếu đã có recommendation pending trong 30 phút
        $alreadyExists = Recommendation::where('prediction_id', $prediction->id)
            ->where('status', RecommendationStatusEnum::PENDING->value)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($alreadyExists) {
            return [];
        }

        $recs = [];

        // ── Phân loại theo mức rủi ro tổng hợp ──────────────────────
        if ($ctx['risk_level'] === 'critical') {
            $recs = array_merge($recs, $this->generateCriticalRecs($prediction, $ctx));
        } elseif ($ctx['risk_level'] === 'high') {
            $recs = array_merge($recs, $this->generateHighRecs($prediction, $ctx));
        } elseif ($ctx['risk_level'] === 'medium') {
            $recs[] = $this->generateMediumRec($prediction, $ctx);
        }

        // ── Đề xuất thêm dựa trên tín hiệu cụ thể ────────────────────

        // Nước đang dâng nhanh → cảnh báo thêm dù severity thấp
        if ($ctx['trend'] >= 0.3 && $ctx['risk_level'] !== 'critical') {
            $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::ALERT->value,
                sprintf(
                    'Mực nước đang dâng nhanh (+%.2f m/h) tại %s — Khuyến nghị theo dõi khẩn',
                    $ctx['trend'],
                    $ctx['zone_name']
                ),
                [
                    'signal'           => 'rapid_water_rise',
                    'water_level_trend' => $ctx['trend'],
                    'current_level_m'  => $ctx['predicted_value'],
                    'confidence'       => $ctx['confidence'],
                    'reasoning'        => [
                        sprintf('Tốc độ dâng nước %.2f m/h vượt ngưỡng cảnh báo (0.3 m/h)', $ctx['trend']),
                        'Mực nước có thể vượt ngưỡng nguy hiểm trong ' . round(max(0.5, ($ctx['danger_threshold'] - $ctx['predicted_value']) / max(0.01, $ctx['trend'])), 1) . ' giờ nữa',
                    ],
                ]
            );
        }

        // Đất bão hòa + mưa nhiều → khuyến nghị thoát nước
        if ($ctx['soil_saturation'] >= 70 && $ctx['rain_6h'] >= 40) {
            $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::SUPPLY_DISPATCH->value,
                sprintf(
                    'Đất bão hòa %.0f%% + mưa tích lũy 6h %.0fmm tại %s — Kích hoạt hệ thống thoát nước khẩn cấp',
                    $ctx['soil_saturation'],
                    $ctx['rain_6h'],
                    $ctx['zone_name']
                ),
                [
                    'signal'          => 'soil_saturation_high',
                    'soil_saturation' => $ctx['soil_saturation'],
                    'rain_6h_mm'      => $ctx['rain_6h'],
                    'confidence'      => $ctx['confidence'],
                    'reasoning'       => [
                        sprintf('Độ bão hòa đất %.0f%% — khả năng thấm gần như bằng 0', $ctx['soil_saturation']),
                        sprintf('Mưa tích lũy 6h: %.0fmm — vượt ngưỡng an toàn 40mm', $ctx['rain_6h']),
                        'Toàn bộ nước mưa sẽ chạy thẳng vào đường phố và kênh thoát nước',
                    ],
                ]
            );
        }

        // Có incident đang xảy ra cùng vùng → ưu tiên điều đội
        if ($ctx['active_incident'] !== null) {
            $recs[] = $this->generateIncidentAwareRec($prediction, $ctx);
        }

        // Escalation: cùng vùng có prediction liên tục tăng → tuyến ưu tiên
        if ($ctx['is_escalating'] && !in_array('critical', array_column($recs, 'type'))) {
            $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::PRIORITY_ROUTE->value,
                sprintf(
                    'Rủi ro ngập tại %s đang leo thang qua %d dự báo liên tiếp — Kích hoạt tuyến ưu tiên',
                    $ctx['zone_name'],
                    $ctx['escalation_count']
                ),
                [
                    'signal'          => 'escalating_risk',
                    'escalation_count' => $ctx['escalation_count'],
                    'confidence'      => $ctx['confidence'],
                    'reasoning'       => [
                        sprintf('%d dự báo liên tiếp cho thấy rủi ro tăng dần tại %s', $ctx['escalation_count'], $ctx['zone_name']),
                        'Xu hướng leo thang yêu cầu can thiệp trước khi đạt đỉnh',
                    ],
                ]
            );
        }

        return $recs;
    }

    // ─────────────────────────────────────────────────────────────────
    // Context builder
    // ─────────────────────────────────────────────────────────────────

    private function buildContext(Prediction $prediction): array
    {
        $floodZone = $prediction->floodZone;
        $inputData = $prediction->input_data ?? [];

        // Trích contributing_factors từ AI service response
        $factors     = $inputData['contributing_factors'] ?? [];
        $tsFeatures  = $inputData['timeseries_features'] ?? [];
        $trend       = (float) ($tsFeatures['water_level_trend'] ?? $factors['rising_trend'] ?? 0.0);
        $rain6h      = (float) ($tsFeatures['rain_6h'] ?? $factors['rain_6h_accum'] ?? 0.0);
        $soilSat     = (float) ($tsFeatures['soil_saturation'] ?? $factors['soil_saturation'] ?? 0.0);

        $probability    = (float) ($prediction->probability ?? 0);
        $confidence     = (float) ($prediction->confidence  ?? 0.5);
        $severity       = $prediction->severity ?? 'low';
        $predictedValue = (float) ($prediction->predicted_value ?? 0);
        $horizonMin     = (int)   ($prediction->horizon_minutes ?? 60);

        $alertThreshold  = (float) ($floodZone?->alert_threshold_m  ?? 1.5);
        $dangerThreshold = (float) ($floodZone?->danger_threshold_m ?? 3.0);
        $zoneName        = $floodZone?->name ?? ($prediction->target_area ?? 'khu vực dự báo');

        // Tổng hợp mức rủi ro từ nhiều tín hiệu
        $riskLevel = $this->computeRiskLevel($severity, $probability, $trend, $rain6h, $soilSat, $predictedValue, $dangerThreshold);

        // Kiểm tra incident đang hoạt động trong cùng flood_zone
        $activeIncident = null;
        if ($floodZone) {
            $activeIncident = Incident::where('flood_zone_id', $floodZone->id)
                ->whereIn('status', ['open', 'in_progress', 'reported'])
                ->orderByDesc('created_at')
                ->first();
        }

        // Đội cứu hộ available trong cùng district
        $availableTeams = 0;
        $districtId = $floodZone?->district_id ?? $prediction->district_id;
        if ($districtId) {
            $availableTeams = RescueTeam::where('status', 'available')
                ->where('district_id', $districtId)
                ->count();
        }

        // Kiểm tra escalation: ≥3 prediction gần đây trong cùng zone với severity tăng
        $escalationCount = 0;
        $isEscalating    = false;
        if ($floodZone) {
            $recentPreds = Prediction::where('flood_zone_id', $floodZone->id)
                ->where('id', '!=', $prediction->id)
                ->where('issued_at', '>=', now()->subHours(3))
                ->orderBy('issued_at')
                ->pluck('probability')
                ->toArray();

            if (count($recentPreds) >= 2) {
                $escalationCount = count($recentPreds);
                $last = end($recentPreds);
                $first = reset($recentPreds);
                $isEscalating = ($last - $first) >= 0.15;
            }
        }

        return [
            'risk_level'       => $riskLevel,
            'severity'         => $severity,
            'probability'      => $probability,
            'confidence'       => $confidence,
            'predicted_value'  => $predictedValue,
            'horizon_min'      => $horizonMin,
            'trend'            => $trend,
            'rain_6h'          => $rain6h,
            'soil_saturation'  => $soilSat,
            'alert_threshold'  => $alertThreshold,
            'danger_threshold' => $dangerThreshold,
            'zone_name'        => $zoneName,
            'flood_zone'       => $floodZone,
            'active_incident'  => $activeIncident,
            'available_teams'  => $availableTeams,
            'is_escalating'    => $isEscalating,
            'escalation_count' => $escalationCount,
        ];
    }

    /**
     * Tổng hợp mức rủi ro từ nhiều tín hiệu (không chỉ severity từ model).
     */
    private function computeRiskLevel(
        string $severity, float $probability, float $trend,
        float $rain6h, float $soilSat, float $predictedValue, float $dangerThreshold
    ): string {
        $score = 0;

        // Severity từ model
        $score += match ($severity) {
            'critical' => 40,
            'high'     => 30,
            'medium'   => 15,
            default    => 5,
        };

        // Probability
        if ($probability >= 0.85) $score += 25;
        elseif ($probability >= 0.7)  $score += 18;
        elseif ($probability >= 0.5)  $score += 10;

        // Tốc độ dâng nước
        if ($trend >= 0.4)  $score += 20;
        elseif ($trend >= 0.2) $score += 12;
        elseif ($trend >= 0.1) $score += 5;

        // Mưa 6h
        if ($rain6h >= 100) $score += 10;
        elseif ($rain6h >= 50)  $score += 6;

        // Đất bão hòa
        if ($soilSat >= 80) $score += 8;
        elseif ($soilSat >= 60)  $score += 4;

        // Mực nước so với ngưỡng nguy hiểm
        if ($predictedValue >= $dangerThreshold) $score += 15;
        elseif ($predictedValue >= $dangerThreshold * 0.75) $score += 8;

        if ($score >= 70) return 'critical';
        if ($score >= 50) return 'high';
        if ($score >= 30) return 'medium';
        return 'low';
    }

    // ─────────────────────────────────────────────────────────────────
    // Generators by risk level
    // ─────────────────────────────────────────────────────────────────

    private function generateCriticalRecs(Prediction $prediction, array $ctx): array
    {
        $zone = $ctx['zone_name'];
        $prob = round($ctx['probability'] * 100);
        $recs = [];

        // 1. Sơ tán
        $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::EVACUATION->value,
            sprintf(
                'KHẨN CẤP: Sơ tán dân cư tại %s — Xác suất ngập %.0f%%, mực nước dự kiến %.2fm (ngưỡng nguy hiểm: %.1fm)',
                $zone, $prob, $ctx['predicted_value'], $ctx['danger_threshold']
            ),
            [
                'action'      => 'immediate_evacuation',
                'urgency'     => 'critical',
                'probability' => $ctx['probability'],
                'confidence'  => $ctx['confidence'],
                'predicted_water_level_m' => $ctx['predicted_value'],
                'danger_threshold_m'      => $ctx['danger_threshold'],
                'horizon_minutes'         => $ctx['horizon_min'],
                'available_rescue_teams'  => $ctx['available_teams'],
                'reasoning'   => $this->buildReasoning($ctx, 'evacuation'),
            ]
        );

        // 2. Cảnh báo khẩn + kích hoạt alert
        $alertRec = $this->makeRec($prediction, RecommendationTypeEnum::ALERT->value,
            sprintf(
                'Phát cảnh báo ngập cấp ĐỎ cho %s — %d phút tới có nguy cơ ngập cao',
                $zone, $ctx['horizon_min']
            ),
            [
                'action'     => 'broadcast_critical_alert',
                'severity'   => 'critical',
                'confidence' => $ctx['confidence'],
                'auto_dispatch_rescue' => $ctx['available_teams'] > 0,
                'reasoning'  => $this->buildReasoning($ctx, 'alert'),
            ]
        );
        if ($this->publishAlert($prediction, $alertRec)) {
            $alertRec->update([
                'status' => 'executed',
                'executed_at' => now(),
            ]);
        }
        $recs[] = $alertRec;

        // 3. Điều đội cứu hộ nếu có
        if ($ctx['available_teams'] > 0) {
            $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::PRIORITY_ROUTE->value,
                sprintf(
                    'Điều %d đội cứu hộ đến %s — Ưu tiên tuyến tiếp cận an toàn',
                    $ctx['available_teams'], $zone
                ),
                [
                    'action'          => 'dispatch_rescue_teams',
                    'teams_available' => $ctx['available_teams'],
                    'target_zone'     => $zone,
                    'confidence'      => $ctx['confidence'],
                    'reasoning'       => $this->buildReasoning($ctx, 'dispatch'),
                ]
            );
        }

        return $recs;
    }

    private function generateHighRecs(Prediction $prediction, array $ctx): array
    {
        $zone = $ctx['zone_name'];
        $prob = round($ctx['probability'] * 100);
        $recs = [];

        // 1. Đổi tuyến + thông báo
        $recs[] = $this->makeRec($prediction, RecommendationTypeEnum::REROUTE->value,
            sprintf(
                'Đề xuất đổi tuyến giao thông tại %s — Xác suất ngập %.0f%% trong %d phút tới',
                $zone, $prob, $ctx['horizon_min']
            ),
            [
                'action'      => 'suggest_reroute',
                'probability' => $ctx['probability'],
                'confidence'  => $ctx['confidence'],
                'horizon_minutes' => $ctx['horizon_min'],
                'reasoning'   => $this->buildReasoning($ctx, 'reroute'),
            ]
        );

        // 2. Cảnh báo khu dân cư
        $alertRec = $this->makeRec($prediction, RecommendationTypeEnum::ALERT->value,
            sprintf(
                'Cảnh báo VÀNG: Người dân %s cần theo dõi tình hình ngập lụt — Xác suất %.0f%%',
                $zone, $prob
            ),
            [
                'action'     => 'notify_residents',
                'severity'   => 'high',
                'radius_km'  => 2,
                'confidence' => $ctx['confidence'],
                'reasoning'  => $this->buildReasoning($ctx, 'alert'),
            ]
        );
        $recs[] = $alertRec;

        return $recs;
    }

    private function generateMediumRec(Prediction $prediction, array $ctx): Recommendation
    {
        return $this->makeRec($prediction, RecommendationTypeEnum::REROUTE->value,
            sprintf(
                'Cập nhật lộ trình di chuyển tại %s — Nguy cơ ngập %.0f%% trong %d phút',
                $ctx['zone_name'],
                round($ctx['probability'] * 100),
                $ctx['horizon_min']
            ),
            [
                'action'      => 'update_routes',
                'severity'    => 'medium',
                'confidence'  => $ctx['confidence'],
                'horizon_minutes' => $ctx['horizon_min'],
                'reasoning'   => $this->buildReasoning($ctx, 'reroute'),
            ]
        );
    }

    private function generateIncidentAwareRec(Prediction $prediction, array $ctx): Recommendation
    {
        $incident = $ctx['active_incident'];
        return $this->makeRec($prediction, RecommendationTypeEnum::SUPPLY_DISPATCH->value,
            sprintf(
                'Sự cố "%s" đang xảy ra tại %s — AI dự báo tình trạng có thể xấu hơn (%.0f%% trong %d phút)',
                $incident->title,
                $ctx['zone_name'],
                round($ctx['probability'] * 100),
                $ctx['horizon_min']
            ),
            [
                'action'        => 'reinforce_incident_response',
                'incident_id'   => $incident->id,
                'incident_status' => $incident->status,
                'probability'   => $ctx['probability'],
                'confidence'    => $ctx['confidence'],
                'available_teams' => $ctx['available_teams'],
                'reasoning'     => [
                    sprintf('Sự cố "%s" đang ở trạng thái: %s', $incident->title, $incident->status),
                    sprintf('Dự báo AI: %.0f%% khả năng ngập tăng trong %d phút tới', round($ctx['probability'] * 100), $ctx['horizon_min']),
                    $ctx['available_teams'] > 0
                        ? sprintf('%d đội cứu hộ sẵn sàng có thể điều động', $ctx['available_teams'])
                        : 'Không có đội cứu hộ sẵn sàng — cần huy động thêm nguồn lực',
                ],
            ],
            $incident->id
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Reasoning builder
    // ─────────────────────────────────────────────────────────────────

    private function buildReasoning(array $ctx, string $type): array
    {
        $lines = [];

        $lines[] = sprintf(
            'Model AI dự báo xác suất ngập %.0f%% (độ tin cậy %.0f%%) trong %d phút tới tại %s',
            round($ctx['probability'] * 100),
            round($ctx['confidence'] * 100),
            $ctx['horizon_min'],
            $ctx['zone_name']
        );

        if ($ctx['predicted_value'] >= $ctx['danger_threshold']) {
            $lines[] = sprintf(
                'Mực nước dự kiến %.2fm ≥ ngưỡng nguy hiểm %.1fm',
                $ctx['predicted_value'], $ctx['danger_threshold']
            );
        } elseif ($ctx['predicted_value'] >= $ctx['alert_threshold']) {
            $lines[] = sprintf(
                'Mực nước dự kiến %.2fm ≥ ngưỡng cảnh báo %.1fm',
                $ctx['predicted_value'], $ctx['alert_threshold']
            );
        }

        if ($ctx['trend'] >= 0.2) {
            $lines[] = sprintf('Nước đang dâng nhanh +%.2f m/h (bình thường < 0.05 m/h)', $ctx['trend']);
        } elseif ($ctx['trend'] <= -0.1) {
            $lines[] = sprintf('Nước đang rút %.2f m/h — tình hình đang cải thiện', abs($ctx['trend']));
        }

        if ($ctx['rain_6h'] >= 50) {
            $lines[] = sprintf('Mưa tích lũy 6 giờ: %.0fmm (ngưỡng nguy hiểm: 50mm)', $ctx['rain_6h']);
        }

        if ($ctx['soil_saturation'] >= 60) {
            $lines[] = sprintf('Đất bão hòa %.0f%% — khả năng thấm rất kém, lũ sẽ tích tụ nhanh', $ctx['soil_saturation']);
        }

        if ($type === 'dispatch' && $ctx['available_teams'] > 0) {
            $lines[] = sprintf('%d đội cứu hộ đang sẵn sàng trong khu vực', $ctx['available_teams']);
        }

        if ($ctx['is_escalating']) {
            $lines[] = sprintf('Rủi ro đang leo thang qua %d dự báo liên tiếp', $ctx['escalation_count']);
        }

        return $lines;
    }

    // ─────────────────────────────────────────────────────────────────
    // Factory helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeRec(
        Prediction $prediction,
        string $type,
        string $description,
        array $details,
        ?int $incidentId = null
    ): Recommendation {
        return Recommendation::create([
            'prediction_id' => $prediction->id,
            'incident_id'   => $incidentId ?? $prediction->incident_id,
            'type'          => $type,
            'description'   => $description,
            'details'       => $details,
            'status'        => RecommendationStatusEnum::PENDING->value,
        ]);
    }

    protected function publishAlert(Prediction $prediction, Recommendation $recommendation): ?Alert
    {
        $floodZone = $prediction->floodZone;
        $zoneId    = $floodZone?->id;

        // Không tạo alert trùng trong 30 phút
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

        $area = $floodZone?->name ?: ($prediction->target_area ?? 'khu vực dự báo');

        $alert = Alert::create([
            'title'       => '[AI] Cảnh báo ngập lụt — ' . $area,
            'description' => sprintf(
                '%s. Xác suất rủi ro %.0f%% trong %d phút tới.',
                $recommendation->description,
                ((float) $prediction->probability) * 100,
                (int) $prediction->horizon_minutes
            ),
            'alert_type'          => AlertTypeEnum::FLOOD_WARNING->value,
            'severity'            => $severity,
            'status'              => AlertStatusEnum::ACTIVE->value,
            'affected_districts'  => $floodZone?->district_id ? [(string) $floodZone->district_id] : [],
            'affected_wards'      => [],
            'affected_flood_zones' => $zoneId ? [(string) $zoneId] : [],
            'radius_km'           => 2,
            'effective_from'      => now(),
            'effective_until'     => now()->addHours(6),
            'source'              => 'ai',
            'related_prediction_id' => $prediction->id,
            'related_incident_id'   => $prediction->incident_id,
        ]);

        // PostGIS geometry nếu có
        $centroid = $floodZone?->centroid_array;
        if ($centroid && DB::connection()->getDriverName() === 'pgsql') {
            try {
                try {
            DB::statement(
                                'UPDATE alerts SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                                [(float) $centroid['lng'], (float) $centroid['lat'], $alert->id]
                            );
        } catch (\Exception $e) {}
            } catch (\Exception) {
                // PostGIS không có — bỏ qua
            }
        }

        return $alert;
    }
}
