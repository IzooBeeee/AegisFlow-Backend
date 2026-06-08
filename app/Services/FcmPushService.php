<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FcmPushService — Gửi notification qua Firebase Cloud Messaging
 */
class FcmPushService
{
    private string $serverKey;
    private string $projectId;
    private string $apiUrl;

    public function __construct()
    {
        $this->serverKey = config('firebase.server_key');
        $this->projectId = config('firebase.project_id');
        $this->apiUrl = 'https://fcm.googleapis.com/fcm/send';
    }

    /**
     * Gửi notification đến một FCM token
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'high'
    ): bool {
        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => $data,
            'android' => [
                'priority' => $priority,
                'notification' => [
                    'channel_id' => 'aegisflow_alerts',
                    'sound' => 'default',
                    'priority' => 'high',
                    'default_vibrate_timings' => true,
                    'default_sound' => true,
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'content-available' => 1,
                    ],
                ],
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert',
                ],
            ],
        ];

        return $this->send($payload);
    }

    /**
     * Gửi notification đến nhiều FCM tokens
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'high'
    ): array {
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => $data,
            'android' => [
                'priority' => $priority,
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ];

        $result = $this->send($payload);

        // Trả về kết quả chi tiết
        return [
            'success' => $result ? count($tokens) : 0,
            'failure' => $result ? 0 : count($tokens),
        ];
    }

    /**
     * Gửi notification đến một topic
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'high'
    ): bool {
        $payload = [
            'to' => "/topics/{$topic}",
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'android' => [
                'priority' => $priority,
            ],
        ];

        return $this->send($payload);
    }

    /**
     * Subscribe device tokens vào topic
     */
    public function subscribeToTopic(array $tokens, string $topic): bool
    {
        $payload = [
            'registration_tokens' => $tokens,
            'to' => "/topics/{$topic}",
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://iid.googleapis.com/iid/v1:batchAdd', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Subscribe to topic failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Unsubscribe device tokens khỏi topic
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): bool
    {
        $payload = [
            'registration_tokens' => $tokens,
            'to' => "/topics/{$topic}",
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://iid.googleapis.com/iid/v1:batchRemove', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Unsubscribe from topic failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Gửi payload đến FCM API
     */
    protected function send(array $payload): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('FCM Push sent', [
                    'success' => $result['success'] ?? 0,
                    'failure' => $result['failure'] ?? 0,
                ]);

                return ($result['success'] ?? 0) > 0;
            }

            Log::error('FCM Push failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('FCM Push exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Kiểm tra FCM service có được cấu hình không
     */
    public function isConfigured(): bool
    {
        return !empty($this->serverKey) && !empty($this->projectId);
    }
}
