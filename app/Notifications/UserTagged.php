<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UserTagged extends Notification
{
    use Queueable;

    protected $message;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Message|\App\Models\Grpmessage $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Determine the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast']; // You can add 'mail' or other channels if desired
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message_id' => $this->message->id,
            'sender_id' => $this->message->user_id,
            'sender_name' => $this->message->user->name,
            'message' => $this->message->message,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'conversation_id' => $this->message->conversation_id ?? null,
            'grpchat_id' => $this->message->grpchat_id ?? null,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'data' => $this->toArray($notifiable),
        ]);
    }
}
