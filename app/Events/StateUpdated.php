<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StateUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public array $state,
        public array $teams,
        public bool $bridge_online
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('bridge-state'); // 👈 nuovo canale
    }

    public function broadcastAs(): string
    {
        return 'state.updated';
    }
}
