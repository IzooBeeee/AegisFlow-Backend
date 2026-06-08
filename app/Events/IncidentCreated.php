<?php

namespace App\Events;

use App\Models\Incident;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IncidentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident
    ) {
        $this->saveNotification();
        $this->dispatchPushNotification();
    }

    protected function saveNotification(): void
    {
        DB::table('notifications')->insert([
            'title' => "Sự cố: {$this->incident->title}",
            'body' => $this->incident->address ?? 'Không xác định',
            'data' => json_encode([
                'id' => $this->incident->id,
                'title' => $this->incident->title,
                'address' => $this->incident->address,
                'severity' => $this->incident->severity,
            ]),
            'notification_type' => 'IncidentCreated',
            'target_type' => 'user',
            'target_id' => $this->incident->created_by ?? 1,
            'channel' => 'web',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Dispatch FCM push notification job
     */
    protected function dispatchPushNotification(): void
    {
        // Gửi notification cho high/critical incidents
        $highSeverity = ['high', 'critical'];
        
        if (in_array($this->incident->severity, $highSeverity)) {
            SendPushNotificationJob::dispatch('incident', [
                'incident_id' => $this->incident->id,
            ]);
        }
    }

    public function broadcastOn(): array
    {
        return ['flood'];
    }

    public function broadcastAs(): string
    {
        return 'IncidentCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->incident->id,
            'title' => $this->incident->title,
            'type' => $this->incident->type,
            'severity' => $this->incident->severity,
            'status' => $this->incident->status,
            'address' => $this->incident->address,
            'water_level_m' => $this->incident->water_level_m,
            'district_id' => $this->incident->district_id,
            'created_at' => $this->incident->created_at?->toIso8601String(),
        ];
    }
}
