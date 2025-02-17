<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Remark;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $grpMessageData;
    public $recipientId;

    public function __construct(array $grpMessageData, int $recipientId)
    {
        $this->grpMessageData = $grpMessageData;
        $this->recipientId = $recipientId;
    }

    public function broadcastOn()
    {
        return new Channel('public-user.' . $this->recipientId);
    }

    public function broadcastWith()
    {
        // Retrieve the nickname from the remarks table
        $remark = Remark::where('user_id', $this->recipientId)
                        ->where('target_id', $this->grpMessageData['user_id'])
                        ->first();

        $nickname = $remark->nickname ?? $this->grpMessageData['sender_name'] ?? 'Unknown';

        // Merge the nickname into the broadcast data
        $payload = array_merge($this->grpMessageData, [
            'sender_nickname' => $nickname,
        ]);

        return $payload;
    }

    public function broadcastAs()
    {
        return 'GroupMessageSent';
    }
}
