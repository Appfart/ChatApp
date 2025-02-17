<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('chat.{conversation_id}', function ($user, $conversation_id) {
    return \App\Models\Conversation::where('id', $conversation_id)
        ->where(function($query) use ($user) {
            $query->where('user_one_id', $user->id)
                  ->orWhere('user_two_id', $user->id);
        })->exists();
});

Broadcast::channel('public-user.{userId}', function ($user, $userId) {
    return true;
});

Broadcast::channel('tickets', function () {
    return true;
});

Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::find($ticketId);
    return $ticket && ($user->id === $ticket->user_id || in_array($user->role, ['support', 'admin']));
});