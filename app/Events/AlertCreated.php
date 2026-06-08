<?php

namespace App\Events;

use App\Models\Alert;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Alert $alert
    ) {
        $this->saveNotification();
        $this->dispatchPushNotification();
    }

    protected function saveNotification(): void
    {
        DB::table('notifications')->insert([
            'alert_id' => $this->alert->id,
            'title' => "Cảnh báo: {$this->alert->title}",
            'body' => $this->alert->description ?? '',
            'data' => json_encode([
                'id' => $this->alert->id,
                'title' => $this->alert->title,
                'description' => $this->alert->description,
                'severity' => $this->alert->severity,
            ]),
            'notification_type' => 'AlertCreated',
            'target_type' => 'user',
            'target_id' => $this->alert->issued_by,
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
        // Chỉ gửi notification nếu là alert có severity cao
        $highSeverity = ['high', 'critical'];
        
        if (in_array($this->alert->severity, $highSeverity)) {
            SendPushNotificationJob::dispatch('alert', [
                'alert_id' => $this->alert->id,
            ]);
        }
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AlertCreated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $geometry = null;
        if (! empty($this->alert->geometry)) {
            if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql') {
                $result = \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT ST_AsGeoJSON(geometry) as geojson FROM alerts WHERE id = ?",
                    [$this->alert->id]
                );
                $geometry = $result?->geojson ? json_decode($result->geojson) : null;
            }
        }

        return [
            'id' => $this->alert->id,
            'alert_number' => $this->alert->alert_number,
            'title' => $this->alert->title,
            'description' => $this->alert->description,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'status' => $this->alert->status,
            'source' => $this->alert->source,
            'geometry' => $geometry,
            'affected_districts' => $this->alert->affected_districts,
            'affected_wards' => $this->alert->affected_wards,
            'affected_flood_zones' => $this->alert->affected_flood_zones,
            'radius_km' => $this->alert->radius_km,
            'effective_from' => $this->alert->effective_from?->toIso8601String(),
            'effective_until' => $this->alert->effective_until?->toIso8601String(),
            'created_at' => $this->alert->created_at?->toIso8601String(),
        ];
    }
}
