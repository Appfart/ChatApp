<!-- resources/views/pages/app/robot/chat.blade.php -->

<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>Chat with {{ $targetUser->realname }}</x-slot>

    <x-slot:headerFiles>
        <style>
            .chat-messages {
                max-height: 400px;
                overflow-y: auto;
                margin-bottom: 20px;
            }
            .message {
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 8px;
                width: fit-content;
                max-width: 70%;
            }
            .message.sent {
                background-color: #d1ffd1;
                align-self: flex-end;
            }
            .message.received {
                background-color: #f1f1f1;
                align-self: flex-start;
            }
        </style>
    </x-slot>

    <div class="container">
        <h1>Chat with {{ $targetUser->realname }}</h1>

        <div class="chat-messages">
            @foreach ($conversation->messages as $message)
                <div class="message {{ $message->user_id == $user->id ? 'sent' : 'received' }}">
                    <p>{{ $message->message }}</p>
                    <small>{{ $message->created_at->format('m/d/Y H:i') }}</small>
                    <button class="p-1 btn btn-sm btn-dark reply-button">回复</button>
                    <button class="p-1 btn btn-sm btn-dark recall-button">撤回</button>
                    <!-- Display reply context if exists -->
                    @if($message->replyTo)
                        <div class="reply-context">
                            <strong>{{ $message->replyTo->user->realname }}:</strong> {{ $message->replyTo->message }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <form action="{{ route('robot.send_message', ['conversation_id' => $conversation->id, 'impersonation_token' => request()->get('impersonation_token')]) }}" method="POST">
            @csrf
            <div class="form-group">
                <textarea name="content" class="form-control" rows="3" placeholder="Type your message here..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>

    <x-slot:footerFiles>
        <!-- Additional JS scripts can be added here -->
    </x-slot>
</x-base-layout>
