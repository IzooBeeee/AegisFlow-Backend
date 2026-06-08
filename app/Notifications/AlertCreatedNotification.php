<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * AlertCreatedNotification — Gửi notification khi có cảnh báo mới
 */
class AlertCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Alert $alert,
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
        $severityLabel = $this->getSeverityLabel($this->alert->severity);

        return [
            'title' => "{$severityLabel}: {$this->alert->title}",
            'body' => $this->alert->description ?? 'Có cảnh báo mới từ hệ thống AegisFlow AI',
            'priority' => $this->alert->severity === 'critical' ? 'high' : 'normal',
            'data' => [
                'type' => 'alert',
                'alert_id' => (string) $this->alert->id,
                'alert_type' => $this->alert->alert_type ?? 'unknown',
                'severity' => $this->alert->severity ?? 'medium',
                'title' => $this->alert->title ?? '',
                'description' => $this->alert->description ?? '',
                'latitude' => $this->extractLatitude(),
                'longitude' => $this->extractLongitude(),
                'radius_km' => (string) ($this->alert->radius_km ?? 0),
                'source' => $this->alert->source ?? 'system',
            ],
        ];
    }

    /**
     * Data cho database notification
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'alert_number' => $this->alert->alert_number,
            'title' => $this->alert->title,
            'description' => $this->alert->description,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'latitude' => $this->extractLatitude(),
            'longitude' => $this->extractLongitude(),
            'radius_km' => $this->alert->radius_km,
            'affected_districts' => $this->alert->affected_districts,
            'source' => $this->alert->source,
            'type' => 'alert_created',
        ];
    }

    /**
     * Trích xuất latitude từ geometry
     */
    protected function extractLatitude(): ?float
    {
        if (! $this->alert->geometry) {
            return null;
        }

        $geometry = is_string($this->alert->geometry) 
            ? json_decode($this->alert->geometry, true) 
            : $this->alert->geometry;

        if (isset($geometry['coordinates'][1])) {
            return $geometry['coordinates'][1];
        }

        return null;
    }

    /**
     * Trích xuất longitude từ geometry
     */
    protected function extractLongitude(): ?float
    {
        if (! $this->alert->geometry) {
            return null;
        }

        $geometry = is_string($this->alert->geometry) 
            ? json_decode($this->alert->geometry, true) 
            : $this->alert->geometry;

        if (isset($geometry['coordinates'][0])) {
            return $geometry['coordinates'][0];
        }

        return null;
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
}
