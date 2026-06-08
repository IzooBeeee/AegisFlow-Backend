<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * IncidentCreatedNotification — Gửi notification khi có sự cố mới
 */
class IncidentCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Incident $incident,
        protected ?string $targetRole = null,
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
        $severityLabel = $this->getSeverityLabel($this->incident->severity);

        return [
            'title' => "{$severityLabel}: {$this->incident->title}",
            'body' => $this->incident->description ?? 'Có sự cố mới cần xử lý',
            'priority' => $this->incident->severity === 'critical' ? 'high' : 'normal',
            'data' => [
                'type' => 'incident',
                'incident_id' => (string) $this->incident->id,
                'incident_type' => $this->incident->type ?? 'unknown',
                'severity' => $this->incident->severity ?? 'medium',
                'status' => $this->incident->status ?? 'reported',
                'title' => $this->incident->title ?? '',
                'description' => $this->incident->description ?? '',
                'latitude' => (string) ($this->incident->latitude ?? ''),
                'longitude' => (string) ($this->incident->longitude ?? ''),
                'address' => $this->incident->address ?? '',
                'water_level_m' => $this->incident->water_level_m ? (string) $this->incident->water_level_m : '',
                'reported_by' => (string) ($this->incident->reported_by ?? ''),
                'source' => $this->incident->source ?? 'system',
            ],
        ];
    }

    /**
     * Data cho database notification
     */
    public function toArray(object $notifiable): array
    {
        return [
            'incident_id' => $this->incident->id,
            'incident_number' => $this->incident->incident_number ?? null,
            'title' => $this->incident->title,
            'description' => $this->incident->description,
            'type' => $this->incident->type,
            'severity' => $this->incident->severity,
            'status' => $this->incident->status,
            'latitude' => $this->incident->latitude,
            'longitude' => $this->incident->longitude,
            'address' => $this->incident->address,
            'water_level_m' => $this->incident->water_level_m,
            'source' => $this->incident->source,
            'type' => 'incident_created',
        ];
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
            default => 'Sự cố',
        };
    }
}
