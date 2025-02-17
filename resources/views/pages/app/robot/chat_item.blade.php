<!-- /home/yellow/chat/resources/views/pages/app/robot/chat_item.blade.php -->
@php
    if($chat['type'] === 'conversation') {
        $conversation = $chat['data'];
        $targetUser = $conversation->name == $user->id ? $conversation->targetUser : $conversation->user;
        $latestMessage = $chat['latest_message'];
    } elseif($chat['type'] === 'grpchat') {
        $grpchat = $chat['data'];
        $latestMessage = $chat['latest_message'];
    }
@endphp

@if($chat['type'] === 'conversation' && $targetUser)
    <div class="person"
         data-chat-type="conversation"
         data-chat-id="{{ $conversation->id }}"
         data-user-id="{{ $targetUser->id }}">
        <div class="user-info">
            <div class="f-head">
                @if($targetUser->avatar)
                    <img src="{{ asset('storage/' . $targetUser->avatar) }}" alt="Avatar" class="avatar">
                @else
                    <div class="avatar-default"></div>
                @endif
            </div>
            <div class="f-body" style="margin-left:5px">
                <div class="meta-info">
                    <span class="user-name">{{ $targetUser->name }}</span>
                    <span class="user-meta-time">
                        {{ $latestMessage ? \Carbon\Carbon::parse($latestMessage->created_at)->diffForHumans() : 'No messages yet' }}
                    </span>
                </div>
                <span class="preview">
                    {{ $latestMessage->message ?? 'No messages yet' }}
                </span>
            </div>
        </div>
    </div>
@elseif($chat['type'] === 'grpchat')
    <div class="person"
         data-chat-type="grpchat"
         data-chat-id="{{ $grpchat->id }}">
        <div class="user-info">
            <div class="f-head">
                @if($grpchat->avatar)
                    <img src="{{ asset('storage/' . $grpchat->avatar) }}" alt="Group Avatar" class="avatar">
                @else
                    <div class="avatar-default"></div>
                @endif
            </div>
            <div class="f-body" style="margin-left:5px">
                <div class="meta-info">
                    <span class="user-name">{{ $grpchat->chatname }}</span>
                    <span class="user-meta-time">
                        {{ $latestMessage ? $latestMessage->created_at->diffForHumans() : 'No messages yet' }}
                    </span>
                </div>
                <span class="preview">
                    {{ $latestMessage->message ?? 'No messages yet' }}
                </span>
                @if($grpchat->owner == $user->id)
                    <div class="settings-icon" style="margin-top: 5px;">
                        <a href="javascript:void(0)" class="open-settings" data-grpchat-id="{{ $grpchat->id }}" title="Group Settings">
                            <!-- SVG Icon Here -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-settings">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0-.33-1.82V9a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif