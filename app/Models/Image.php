<?php

namespace App\Models;

use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use BroadcastsEvents;

    protected $fillable = [
        'prompt',
        'webcam_image_path',
        'generated_image_path',
        'status',
        'error_message',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function broadcastOn(string $event): array
    {
        return [
            new Channel('images'),
        ];
    }

    public function broadcastWith(string $event): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'webcam_image_path' => $this->webcam_image_path,
            'generated_image_path' => $this->generated_image_path,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
