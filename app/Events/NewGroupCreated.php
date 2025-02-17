<?php

namespace App\Events;

use App\Models\Grpchat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Support\Facades\Log;

class NewGroupCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $grpchat;
    public $members;

    public function __construct(Grpchat $grpchat, array $members)
    {
        $this->grpchat = $grpchat;
        $this->members = $members;

        // Log when the event is created
        Log::info('NewGroupCreated Event Initialized', [
            'grpchat_id' => $grpchat->id,
            'members' => $members,
        ]);
    }

    public function broadcastOn()
    {
        $channels = collect($this->members)->map(function ($memberId) {
            return new Channel('public-user.' . $memberId);
        })->all();

        // Log the channels being broadcasted to
        Log::info('Broadcasting to Channels', ['channels' => $channels]);

        return $channels;
    }

    public function broadcastAs()
    {
        return 'NewGroupCreated';
    }
}