<?php

namespace App\Events;

use App\Models\RescueRequest;
use App\Models\User;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RescueRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $oldStatus;
    public string $newStatus;
    public ?User $reporter;

    public function __construct(
        public RescueRequest $rescueRequest,
        string $oldStatus = '',
        string $newStatus = '',
        ?User $reporter = null
    ) {
        $this->oldStatus = $oldStatus ?: $rescueRequest->getOriginal('status') ?? '';
        $this->newStatus = $newStatus ?: $rescueRequest->status;
        $this->reporter = $reporter;
        $this->dispatchPushNotification();
    }

    /**
     * Dispatch FCM push notification cho reporter
     */
    protected function dispatchPushNotification(): void
    {
        // Gửi notification cho người tạo rescue request
        if ($this->rescueRequest->reported_by) {
            $reporter = User::find($this->rescueRequest->reported_by);
            if ($reporter) {
                $tokens = $reporter->devices()
                    ->active()
                    ->notificationsEnabled()
                    ->pluck('fcm_token')
                    ->filter()
                    ->toArray();

                if (! empty($tokens)) {
                    $statusLabel = match ($this->newStatus) {
                        'assigned' => 'Đã tiếp nhận',
                        'dispatched' => 'Đã điều động',
                        'en_route' => 'Đang di chuyển',
                        'on_site' => 'Đội cứu hộ đã đến',
                        'rescued' => 'Đã giải cứu',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                        default => $this->newStatus,
                    };

                    $title = "Cập nhật yêu cầu cứu hộ #{$this->rescueRequest->id}";
                    $body = "Trạng thái: {$statusLabel}";

                    $fcm = app(\App\Services\FcmPushService::class);
                    $fcm->sendToTokens($tokens, $title, $body, [
                        'type' => 'rescue_status_update',
                        'rescue_id' => (string) $this->rescueRequest->id,
                        'old_status' => $this->oldStatus,
                        'new_status' => $this->newStatus,
                    ]);
                }
            }
        }
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('flood')
        ];
    }

    public function broadcastAs(): string
    {
        return 'RescueRequestUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->rescueRequest->id,
            'urgency' => $this->rescueRequest->urgency,
            'status' => $this->rescueRequest->status,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'people_count' => $this->rescueRequest->people_count,
            'address' => $this->rescueRequest->address,
            'assigned_team_id' => $this->rescueRequest->assigned_team_id,
            'updated_at' => $this->rescueRequest->updated_at?->toIso8601String(),
        ];
    }
}
