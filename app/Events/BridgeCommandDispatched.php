<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class BridgeCommandDispatched implements ShouldBroadcastNow
{
    public function __construct(public readonly array $command) {}

    public function broadcastOn(): Channel
    {
        return new Channel('bridge-commands');
    }

    public function broadcastAs(): string
    {
        return 'command.issued';
    }
}
