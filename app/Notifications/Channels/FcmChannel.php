<?php

namespace App\Notifications\Channels;

use App\Services\FcmPushService;
use Illuminate\Notifications\Notification;

/**
 * FcmChannel — Laravel Notification Channel cho Firebase Cloud Messaging
 * 
 * Sử dụng:
 * 1. Tạo notification class kế thừa Notification
 * 2. Thêm method toFcm() trả về payload
 * 3. Thêm 'fcm' vào via() return array
 */
final class FcmChannel
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    /**
     * Gửi notification qua FCM
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // Kiểm tra notification có method toFcm không
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        // Lấy FCM token từ notifiable (User model)
        $token = $this->getFcmToken($notifiable);
        if (! $token || $token === '') {
            return;
        }

        // Lấy payload từ notification
        /** @var array{title?: string, body?: string, data?: array<string, mixed>, priority?: string} $payload */
        $payload = $notification->toFcm($notifiable);

        // Gửi notification
        $this->fcm->sendToToken(
            $token,
            (string) ($payload['title'] ?? ''),
            (string) ($payload['body'] ?? ''),
            $payload['data'] ?? [],
            $payload['priority'] ?? 'high'
        );
    }

    /**
     * Lấy FCM token từ notifiable
     */
    protected function getFcmToken(object $notifiable): ?string
    {
        // Thử lấy từ attribute fcm_token trực tiếp
        if (isset($notifiable->fcm_token) && $notifiable->fcm_token !== '') {
            return $notifiable->fcm_token;
        }

        // Thử lấy từ devices relationship
        if (method_exists($notifiable, 'devices')) {
            $device = $notifiable->devices()->active()->first();
            if ($device && $device->fcm_token) {
                return $device->fcm_token;
            }
        }

        return null;
    }
}
