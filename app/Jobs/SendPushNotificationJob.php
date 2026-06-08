<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\RescueRequest;
use App\Models\Prediction;
use App\Services\NotificationBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendPushNotificationJob — Gửi FCM notification bất đồng bộ
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $type,
        public array $data = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationBroadcastService $service): void
    {
        $count = match ($this->type) {
            'alert' => $service->sendAlertNotification(
                Alert::findOrFail($this->data['alert_id'])
            ),
            'incident' => $service->sendIncidentNotification(
                Incident::findOrFail($this->data['incident_id'])
            ),
            'rescue' => $service->sendRescueRequestNotification(
                RescueRequest::findOrFail($this->data['rescue_id'])
            ),
            'flood_warning' => $service->sendFloodWarningNotification(
                Prediction::findOrFail($this->data['prediction_id']),
                $this->data['affected_districts'] ?? [],
                $this->data['risk_level'] ?? 'medium'
            ),
            default => 0,
        };

        \Log::info("Push notification sent", [
            'type' => $this->type,
            'recipients' => $count,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error("Push notification failed", [
            'type' => $this->type,
            'data' => $this->data,
            'error' => $exception->getMessage(),
        ]);
    }
}
