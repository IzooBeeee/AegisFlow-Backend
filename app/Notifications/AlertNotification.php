<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public string $severity = 'medium'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity,
            'alert_id' => $this->data['alert_id'] ?? null,
            'incident_id' => $this->data['incident_id'] ?? null,
            'type' => 'alert',
        ];
    }
}
