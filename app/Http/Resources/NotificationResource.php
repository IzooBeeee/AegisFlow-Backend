<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'notification_type' => $this->notification_type,
            'target_type' => $this->target_type,
            'channel' => $this->channel,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'alert' => $this->whenLoaded('alert', fn () => [
                'id' => $this->alert->id,
                'title' => $this->alert->title,
                'severity' => $this->alert->severity,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
