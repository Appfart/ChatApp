<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class NewFriendRequestEvent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $friendId;

    public function __construct($friendId)
    {
        $this->friendId = $friendId;
    }

    public function broadcastOn()
    {
        return new Channel('public-user.' . $this->friendId);
        return $channel;
    }

    public function broadcastAs()
    {
        $eventName = 'NewFriendRequest';
        return $eventName;
    }
}
