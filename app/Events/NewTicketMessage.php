<?php

namespace App\Events;

use App\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTicketMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $ticketStatus;

    public function __construct(TicketMessage $message)
    {
        // Ensure relationships are loaded to prevent null values
        $this->message = $message->load('user', 'ticket');
        $this->ticketStatus = $this->message->ticket->status;
    }

    public function broadcastOn()
    {
        return [
            new Channel('tickets'), // General tickets channel
            new Channel('ticket.' . $this->message->ticket_id), // Specific ticket channel
        ];
    }
    
    public function broadcastWith()
    {
        $payload = [
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'message' => $this->message->message,
                'image_url' => $this->message->image_url ? asset($this->message->image_url) : null,
                'created_at' => $this->message->created_at->toDateTimeString(),
                'user' => [
                    'id' => $this->message->user->id,
                    'realname' => $this->message->user->realname,
                    'name' => $this->message->user->name,
                ],
            ],
            'ticket_status' => ucfirst($this->ticketStatus), // Capitalize the first letter for consistency
        ];

        // Log the payload for debugging
        \Log::info('Broadcasting payload:', $payload);

        return $payload;
    }

    public function broadcastAs()
    {
        return 'NewTicketMessage'; // Explicitly define the event name
    }
}
