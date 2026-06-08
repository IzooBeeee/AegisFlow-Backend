<?php

namespace App\Notifications;

use App\Models\FloodZone;
use App\Models\Prediction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * FloodWarningNotification — Gửi notification cảnh báo ngập từ AI prediction
 */
class FloodWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Prediction $prediction,
        protected FloodZone $floodZone,
        protected float $riskScore,
        protected string $riskLevel,
    ) {}

    /**
     * Các channels để gửi notification
     */
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    /**
     * Payload cho FCM
     */
    public function toFcm(object $notifiable): array
    {
        $riskLabel = $this->getRiskLabel();
        $zoneName = $this->floodZone->name ?? 'Khu vực ngập';

        return [
            'title' => "{$riskLabel}: Nguy cơ ngập tại {$zoneName}",
            'body' => $this->buildBody(),
            'priority' => $this->riskLevel === 'critical' || $this->riskLevel === 'high' ? 'high' : 'normal',
            'data' => [
                'type' => 'flood_warning',
                'prediction_id' => (string) $this->prediction->id,
                'flood_zone_id' => (string) $this->floodZone->id,
                'zone_name' => $zoneName,
                'risk_level' => $this->riskLevel,
                'risk_score' => (string) number_format($this->riskScore, 2),
                'latitude' => (string) ($this->floodZone->latitude ?? ''),
                'longitude' => (string) ($this->floodZone->longitude ?? ''),
                'water_level_current' => $this->prediction->predicted_water_level 
                    ? (string) $this->prediction->predicted_water_level 
                    : '',
                'water_level_prediction' => $this->prediction->predicted_water_level 
                    ? (string) $this->prediction->predicted_water_level 
                    : '',
                'expected_time' => $this->prediction->prediction_time?->toIso8601String() ?? '',
                'affected_wards' => json_encode($this->floodZone->affected_wards ?? []),
                'recommended_action' => $this->getRecommendedAction(),
            ],
        ];
    }

    /**
     * Data cho database notification
     */
    public function toArray(object $notifiable): array
    {
        return [
            'prediction_id' => $this->prediction->id,
            'flood_zone_id' => $this->floodZone->id,
            'zone_name' => $this->floodZone->name,
            'risk_level' => $this->riskLevel,
            'risk_score' => $this->riskScore,
            'latitude' => $this->floodZone->latitude,
            'longitude' => $this->floodZone->longitude,
            'water_level_current' => $this->prediction->current_water_level,
            'water_level_prediction' => $this->prediction->predicted_water_level,
            'prediction_time' => $this->prediction->prediction_time?->toIso8601String(),
            'affected_wards' => $this->floodZone->affected_wards,
            'recommended_action' => $this->getRecommendedAction(),
            'type' => 'flood_warning',
        ];
    }

    /**
     * Xây dựng nội dung notification
     */
    protected function buildBody(): string
    {
        $riskLabel = $this->getRiskLabel();
        $zoneName = $this->floodZone->name ?? 'khu vực';
        $waterLevel = $this->prediction->predicted_water_level 
            ? number_format($this->prediction->predicted_water_level, 1) . 'm' 
            : 'mực nước dự báo không xác định';

        return match ($this->riskLevel) {
            'critical' => "Nguy hiểm! Nguy cơ ngập rất cao tại {$zoneName}. Mực nước dự báo: {$waterLevel}. Hãy sơ tán ngay!",
            'high' => "Cảnh báo! Nguy cơ ngập cao tại {$zoneName}. Mực nước dự báo: {$waterLevel}. Chuẩn bị sơ tán.",
            'medium' => "Thận trọng! Nguy cơ ngập trung bình tại {$zoneName}. Mực nước dự báo: {$waterLevel}.",
            'low' => "Theo dõi! Nguy cơ ngập thấp tại {$zoneName}. Mực nước dự báo: {$waterLevel}.",
            default => "Có thông tin dự báo ngập mới cho {$zoneName}. Mực nước dự báo: {$waterLevel}.",
        };
    }

    /**
     * Lấy nhãn mức độ rủi ro
     */
    protected function getRiskLabel(): string
    {
        return match ($this->riskLevel) {
            'critical' => 'Nguy hiểm',
            'high' => 'Cao',
            'medium' => 'Trung bình',
            'low' => 'Thấp',
            default => 'Thông báo',
        };
    }

    /**
     * Lấy hành động khuyến nghị
     */
    protected function getRecommendedAction(): string
    {
        return match ($this->riskLevel) {
            'critical' => 'evacuate_immediately',
            'high' => 'prepare_evacuation',
            'medium' => 'stay_alert',
            'low' => 'monitor',
            default => 'monitor',
        };
    }
}
