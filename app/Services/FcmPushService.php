<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FcmPushService — Firebase Cloud Messaging v1 API (OAuth2 Service Account)
 */
class FcmPushService
{
    private string $projectId;

    private string $credentialsPath;

    private string $fcmUrl;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');
        $this->credentialsPath = config('firebase.credentials_path');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
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
        $message = $this->buildMessage($token, $title, $body, $data, $priority);
        return $this->sendV1(['message' => $message]);
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

        $success = 0;
        $failure = 0;

        foreach ($tokens as $token) {
            $message = $this->buildMessage($token, $title, $body, $data, $priority);
            if ($this->sendV1(['message' => $message])) {
                $success++;
            } else {
                $failure++;
            }
        }

        return ['success' => $success, 'failure' => $failure];
    }

    /**
     * Kiểm tra FCM service có được cấu hình không
     */
    public function isConfigured(): bool
    {
        return ! empty($this->projectId) && file_exists($this->credentialsPath);
    }

    /**
     * Build FCM v1 message payload cho một token
     */
    protected function buildMessage(
        string $token,
        string $title,
        string $body,
        array $data,
        string $priority
    ): array {
        // FCM v1 data values must be strings
        $stringData = array_map('strval', $data);

        return [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $stringData,
            'android' => [
                'priority' => strtoupper($priority) === 'HIGH' ? 'HIGH' : 'NORMAL',
                'notification' => [
                    'channel_id' => config('firebase.android_channel_id', 'aegisflow_alerts'),
                    'sound' => 'default',
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'content-available' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Gửi message qua FCM v1 API
     */
    protected function sendV1(array $payload): bool
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                Log::info('FCM v1 push sent', ['name' => $response->json('name')]);
                return true;
            }

            Log::error('FCM v1 push failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('FCM v1 push exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Lấy OAuth2 access token từ Service Account (cached 55 phút)
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            return $this->fetchAccessToken();
        });
    }

    /**
     * Tạo JWT và đổi lấy access token từ Google OAuth2
     */
    protected function fetchAccessToken(): ?string
    {
        if (! file_exists($this->credentialsPath)) {
            Log::error('FCM credentials file not found', ['path' => $this->credentialsPath]);
            return null;
        }

        $creds = json_decode(file_get_contents($this->credentialsPath), true);
        if (empty($creds['private_key']) || empty($creds['client_email'])) {
            Log::error('FCM credentials missing private_key or client_email');
            return null;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";
        $privateKey = openssl_pkey_get_private($creds['private_key']);
        if (! $privateKey) {
            Log::error('FCM failed to load private key');
            return null;
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('FCM JWT signing failed');
            return null;
        }

        $jwt = "{$signingInput}.".$this->base64UrlEncode($signature);

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('FCM token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('FCM token exchange exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
