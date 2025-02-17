<?php

namespace App\Events;

use App\Models\Grpchat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class GroupMemberUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $grpchat;
    public $updatedMembers;

    /**
     * Create a new event instance.
     *
     * @param \App\Models\Grpchat $grpchat
     * @param array $updatedMembers
     */
    public function __construct(Grpchat $grpchat, array $updatedMembers)
    {
        $this->grpchat = $grpchat;
        $this->updatedMembers = $updatedMembers;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('group.' . $this->grpchat->id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'GroupMemberUpdated';
    }
}
