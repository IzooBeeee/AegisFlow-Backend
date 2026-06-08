<?php

namespace App\Notifications;

use App\Models\RescueRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * RescueStatusNotification — Gửi notification cập nhật trạng thái yêu cầu cứu hộ
 */
class RescueStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected RescueRequest $rescueRequest,
        protected string $oldStatus,
        protected string $newStatus,
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
        $statusLabel = $this->getStatusLabel($this->newStatus);

        return [
            'title' => "Cập nhật: {$statusLabel}",
            'body' => $this->rescueRequest->description 
                ? "Yêu cầu cứu hộ #{$this->rescueRequest->id}: {$this->rescueRequest->description}"
                : "Yêu cầu cứu hộ #{$this->rescueRequest->id} đã được cập nhật sang trạng thái {$statusLabel}",
            'priority' => 'high',
            'data' => [
                'type' => 'rescue',
                'rescue_id' => (string) $this->rescueRequest->id,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'latitude' => $this->rescueRequest->latitude ? (string) $this->rescueRequest->latitude : '',
                'longitude' => $this->rescueRequest->longitude ? (string) $this->rescueRequest->longitude : '',
                'address' => $this->rescueRequest->address ?? '',
                'victim_name' => $this->rescueRequest->victim_name ?? '',
                'victim_phone' => $this->rescueRequest->victim_phone ?? '',
                'priority_level' => $this->rescueRequest->priority_level ?? 'medium',
            ],
        ];
    }

    /**
     * Data cho database notification
     */
    public function toArray(object $notifiable): array
    {
        return [
            'rescue_id' => $this->rescueRequest->id,
            'title' => "Cập nhật trạng thái",
            'description' => $this->rescueRequest->description,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'status_label' => $this->getStatusLabel($this->newStatus),
            'latitude' => $this->rescueRequest->latitude,
            'longitude' => $this->rescueRequest->longitude,
            'address' => $this->rescueRequest->address,
            'victim_name' => $this->rescueRequest->victim_name,
            'priority_level' => $this->rescueRequest->priority_level,
            'type' => 'rescue_status_update',
        ];
    }

    /**
     * Lấy nhãn trạng thái
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xử lý',
            'assigned' => 'Đã tiếp nhận',
            'dispatched' => 'Đã điều động',
            'en_route' => 'Đang di chuyển',
            'on_site' => 'Tại hiện trường',
            'rescued' => 'Đã giải cứu',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'failed' => 'Thất bại',
            default => $status,
        };
    }
}
