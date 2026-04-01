<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ProjectionUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public readonly ?array $question,
        public readonly ?array $selected_answer,
        public readonly string $winner_color = 'yellow',
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('projection');
    }

    public function broadcastAs(): string
    {
        return 'projection.updated';
    }
}
