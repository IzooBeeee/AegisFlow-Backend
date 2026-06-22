<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\Prediction;
use App\Models\RescueRequest;
use App\Models\UserDevice;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * NotificationBroadcastService — Gửi notification đến nhiều users
 */
class NotificationBroadcastService
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    /**
     * Gửi notification cho users theo role
     */
    public function sendToRole(
        string $role,
        string $title,
        string $body,
        array $data = []
    ): int {
        try {
            $tokens = UserDevice::whereHas('user', function ($query) use ($role) {
                $query->role($role);
            })
                ->active()
                ->notificationsEnabled()
                ->pluck('fcm_token')
                ->filter()
                ->toArray();
        } catch (RoleDoesNotExist) {
            return 0;
        }

        if (empty($tokens)) {
            return 0;
        }

        $result = $this->fcm->sendToTokens($tokens, $title, $body, $data);

        return $result['success'];
    }

    /**
     * Gửi notification cho users theo districts
     */
    public function sendToDistricts(
        array $districtIds,
        string $title,
        string $body,
        array $data = []
    ): int {
        $tokens = UserDevice::whereHas('user', function ($query) use ($districtIds) {
            $query->whereIn('district_id', $districtIds);
        })
            ->active()
            ->notificationsEnabled()
            ->pluck('fcm_token')
            ->filter()
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        $result = $this->fcm->sendToTokens($tokens, $title, $body, $data);

        return $result['success'];
    }

    /**
     * Gửi notification cho tất cả users
     */
    public function sendToAllUsers(
        string $title,
        string $body,
        array $data = []
    ): int {
        $tokens = UserDevice::active()
            ->notificationsEnabled()
            ->pluck('fcm_token')
            ->filter()
            ->toArray();

        if (empty($tokens)) {
            return 0;
        }

        $result = $this->fcm->sendToTokens($tokens, $title, $body, $data);

        return $result['success'];
    }

    /**
     * Gửi AlertCreated notification
     */
    public function sendAlertNotification(Alert $alert): int
    {
        $severityLabel = $this->getSeverityLabel($alert->severity);
        $title = "{$severityLabel}: {$alert->title}";
        $body = $alert->description ?? 'Có cảnh báo mới từ hệ thống';

        $data = [
            'type'       => 'alert',
            'id'         => (string) $alert->id,   // Mobile dùng 'id' để navigate
            'alert_id'   => (string) $alert->id,
            'severity'   => $alert->severity,
            'alert_type' => $alert->alert_type ?? 'unknown',
        ];

        // Gửi cho users trong affected districts
        $affectedDistricts = $alert->affected_districts ?? [];
        if (! empty($affectedDistricts)) {
            $sent = $this->sendToDistricts($affectedDistricts, $title, $body, $data);
            if ($sent > 0) {
                return $sent;
            }
            // Fallback: không có user nào trong district có FCM token → gửi cho tất cả citizens
        }

        // Fallback: gửi cho tất cả citizens (khi district rỗng HOẶC không khớp user nào)
        $sent = $this->sendToRole('citizen', $title, $body, $data);
        if ($sent > 0) {
            return $sent;
        }

        // Final fallback: gửi cho tất cả user có FCM token (kể cả không phải citizen)
        return $this->sendToAllUsers($title, $body, $data);
    }

    /**
     * Gửi IncidentCreated notification
     */
    public function sendIncidentNotification(Incident $incident): int
    {
        $severityLabel = $this->getSeverityLabel($incident->severity);
        $title = "{$severityLabel}: {$incident->title}";
        $body = $incident->description ?? 'Có sự cố mới';

        $data = [
            'type' => 'incident',
            'incident_id' => (string) $incident->id,
            'severity' => $incident->severity,
            'latitude' => $incident->latitude ? (string) $incident->latitude : '',
            'longitude' => $incident->longitude ? (string) $incident->longitude : '',
        ];

        // Gửi cho operators và admins
        $success = 0;
        $success += $this->sendToRole('city_admin', $title, $body, $data);
        $success += $this->sendToRole('rescue_operator', $title, $body, $data);

        // Nếu incident ở district cụ thể
        if ($incident->district_id) {
            $success += $this->sendToDistricts([$incident->district_id], $title, $body, $data);
        }

        return $success;
    }

    /**
     * Gửi RescueRequest notification cho operators
     */
    public function sendRescueRequestNotification(RescueRequest $request): int
    {
        $title = 'Yêu cầu cứu hộ mới';
        $body = $request->description
            ? substr($request->description, 0, 100)
            : 'Có yêu cầu cứu hộ tại vị trí của bạn';

        $data = [
            'type' => 'rescue',
            'rescue_id' => (string) $request->id,
            'priority' => $request->priority_level ?? 'medium',
            'latitude' => $request->latitude ? (string) $request->latitude : '',
            'longitude' => $request->longitude ? (string) $request->longitude : '',
        ];

        return $this->sendToRole('rescue_operator', $title, $body, $data)
            + $this->sendToRole('emergency', $title, $body, $data);
    }

    /**
     * Gửi FloodWarning notification
     */
    public function sendFloodWarningNotification(
        Prediction $prediction,
        array $affectedDistrictIds,
        string $riskLevel
    ): int {
        $zoneName = $prediction->floodZone?->name ?? 'Khu vực ngập';
        $riskLabel = $this->getRiskLabel($riskLevel);

        $title = "{$riskLabel}: Nguy cơ ngập tại {$zoneName}";
        $body = $this->buildFloodWarningBody($prediction, $riskLevel);

        $data = [
            'type' => 'flood_warning',
            'prediction_id' => (string) $prediction->id,
            'flood_zone_id' => (string) ($prediction->flood_zone_id ?? ''),
            'risk_level' => $riskLevel,
            'latitude' => (string) ($prediction->floodZone?->latitude ?? ''),
            'longitude' => (string) ($prediction->floodZone?->longitude ?? ''),
        ];

        $success = 0;
        $success += $this->sendToDistricts($affectedDistrictIds, $title, $body, $data);
        $success += $this->sendToRole('city_admin', $title, $body, $data);
        $success += $this->sendToRole('emergency', $title, $body, $data);

        return $success;
    }

    /**
     * Lấy nhãn mức độ nghiêm trọng
     */
    protected function getSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Nguy hiểm',
            'high' => 'Cao',
            'medium' => 'Trung bình',
            'low' => 'Thấp',
            default => 'Thông báo',
        };
    }

    /**
     * Lấy nhãn mức độ rủi ro
     */
    protected function getRiskLabel(string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'Nguy hiểm',
            'high' => 'Cao',
            'medium' => 'Trung bình',
            'low' => 'Thấp',
            default => 'Theo dõi',
        };
    }

    /**
     * Build flood warning body
     */
    protected function buildFloodWarningBody(Prediction $prediction, string $riskLevel): string
    {
        $zoneName = $prediction->floodZone?->name ?? 'khu vực';
        $waterLevel = $prediction->predicted_water_level
            ? number_format($prediction->predicted_water_level, 1).'m'
            : 'mực nước dự báo không xác định';

        return match ($riskLevel) {
            'critical' => "Nguy hiểm! Nguy cơ ngập rất cao tại {$zoneName}. Mực nước: {$waterLevel}. Hãy sơ tán ngay!",
            'high' => "Cảnh báo! Nguy cơ ngập cao tại {$zoneName}. Mực nước: {$waterLevel}. Chuẩn bị sơ tán.",
            'medium' => "Thận trọng! Nguy cơ ngập trung bình tại {$zoneName}. Mực nước: {$waterLevel}.",
            default => "Theo dõi! Nguy cơ ngập thấp tại {$zoneName}. Mực nước: {$waterLevel}.",
        };
    }
}
