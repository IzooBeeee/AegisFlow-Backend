<?php

namespace App\Events;

use App\Models\RescueRequest;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RescueRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RescueRequest $rescueRequest
    ) {
        $this->dispatchPushNotification();
    }

    /**
     * Dispatch FCM push notification job
     */
    protected function dispatchPushNotification(): void
    {
        SendPushNotificationJob::dispatch('rescue', [
            'rescue_id' => $this->rescueRequest->id,
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    public function broadcastAs(): string
    {
        return 'RescueRequestCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->rescueRequest->id,
            'urgency' => $this->rescueRequest->urgency,
            'status' => $this->rescueRequest->status,
            'people_count' => $this->rescueRequest->people_count,
            'contact_phone' => $this->rescueRequest->contact_phone,
            'address' => $this->rescueRequest->address,
            'created_at' => $this->rescueRequest->created_at?->toIso8601String(),
        ];
    }
}
