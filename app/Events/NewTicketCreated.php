<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;

    public function __construct(Ticket $ticket)
    {
        // Ensure the latestMessage relationship is loaded
        $this->ticket = $ticket->load('latestMessage.user');
    }

    public function broadcastOn()
    {
        return new Channel('tickets');
    }

    public function broadcastWith()
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_id' => $this->ticket->id,
                'subject' => $this->ticket->subject,
                'status' => $this->ticket->status,
                'created_at' => $this->ticket->created_at,
                'updated_at' => $this->ticket->updated_at,
                'message' => $this->ticket->latestMessage ? $this->ticket->latestMessage->message : null,
                'user' => [
                    'id' => $this->ticket->user->id,
                    'realname' => $this->ticket->user->realname,
                    'name' => $this->ticket->user->name,
                ],
            ],
        ];
    }
}
