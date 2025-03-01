<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Remark;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $recipientId;
    public $type;
    public $senderId;

    public function __construct(Message $message, $recipientId, $senderId, $type = 'conversation')
    {
        $this->message = $message;
        $this->recipientId = $recipientId;
        $this->senderId = $senderId;
        $this->type = $type;
    }
    
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastOn()
    {
        $channel = new Channel('public-user.' . $this->recipientId);

        //Log::info('MessageSent broadcast channel', [
        //    'channel_name' => $channel->name,
        //]);

        return $channel;
    }

    public function broadcastWith()
    {
        // Retrieve the nickname for the sender
        $remark = Remark::where('user_id', $this->recipientId)
                        ->where('target_id', $this->senderId)
                        ->first();
    
        $nickname = $remark->nickname ?? $this->message->user->realname;
    
        // Retrieve the nickname for the reply-to user
        $replyToRemark = $this->message->replyTo 
            ? Remark::where('user_id', $this->recipientId)
                    ->where('target_id', $this->message->replyTo->user->id)
                    ->first()
            : null;
    
        $replyToNickname = $replyToRemark 
            ? $replyToRemark->nickname 
            : ($this->message->replyTo->user->realname ?? 'Unknown');
    
        $payload = [
            'type' => $this->type,
            'conversation_id' => $this->message->conversation_id,
            'id' => $this->message->id,
            'message' => $this->message->status == 0 
                ? '信息已经撤回' 
                : ($this->message->message 
                    ?? ($this->message->image_url ? '图片信息'
                        : ($this->message->audio_url ? '音频信息'
                            : ($this->message->doc_url ? '文档信息'
                                : ($this->message->video_url ? '视频信息' : '未知信息'))))),
            'image_url' => $this->message->image_url ?? null,
            'doc_url' => $this->message->doc_url ?? null,
            'video_url' => $this->message->video_url ?? null,
            'user_id' => $this->message->user->id ?? null,
            'sender_name' => $this->message->user->realname ?? 'Unknown',
            'sender_avatar' => $this->message->user->avatar ?? 'default-avatar.png',
            'created_at' => $this->message->created_at->toIso8601String(),
            'reply_to_id' => $this->message->reply_to_id,
            'reply_to_user_name' => $this->message->replyTo 
                ? $replyToNickname 
                : null,
            'reply_to_message' => $this->message->replyTo 
                ? ($this->message->replyTo->message 
                    ?? ($this->message->replyTo->image_url ? '图片信息'
                        : ($this->message->replyTo->audio_url ? '音频信息'
                            : ($this->message->replyTo->doc_url ? '文档信息'
                                : ($this->message->replyTo->video_url ? '视频信息' : '未知信息')))))
                : null,
            'sender_nickname' => $nickname ?? 'none',
            'status' => $this->message->status,
        ];
    
        // Log the payload being broadcasted
        //Log::info('MessageSent broadcast payload', $payload);
    
        return $payload;
    }

}
