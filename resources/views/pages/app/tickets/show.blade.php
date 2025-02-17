{{-- /home/yellow/chat/resources/views/pages/app/tickets/show.blade.php --}}
<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        Ticket #{{ $ticket->id }} - {{ $ticket->subject }}
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/components/modal.scss'])
        @vite(['resources/scss/light/assets/apps/contacts.scss'])

        @vite(['resources/scss/dark/assets/components/modal.scss'])
        @vite(['resources/scss/dark/assets/apps/contacts.scss'])
        
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <style>
            /* General message wrapper styling */
            .message-wrapper {
                display: flex;
                margin-top: 1rem;
            }
            
            /* Left-aligned message styling */
            .message-left {
                justify-content: flex-start;
            }
            
            .message-left .card {
                background-color: #f0f0f0;
                border-radius: 15px 15px 15px 0;
                max-width: 60%;
                align-self: flex-start;
            }
            
            /* Right-aligned message styling */
            .message-right {
                justify-content: flex-end;
            }
            
            .message-right .card {
                background-color: #ffffff;
                border-radius: 15px 15px 0 15px;
                max-width: 60%;
                align-self: flex-end;
            }
            
            /* Card header and body styles */
            .card-header {
                font-weight: bold;
                font-size: 0.9rem;
                color: #333;
            }
            
            .card-body {
                font-size: 1rem;
                color: #555;
            }
            
            /* Additional styling for better visuals */
            .message-left .card-header,
            .message-left .card-body {
                text-align: left;
            }
            
            .message-right .card-header,
            .message-right .card-body {
                text-align: right;
            }
        </style>
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <div class="container mt-4">
        <h1>Ticket #{{ $ticket->id }} - {{ $ticket->subject }}</h1>
        <p>Status: {{ ucfirst($ticket->status) }}</p>
        <hr>
        <h4>Messages</h4>
        <div id="messages">
            @foreach($ticket->messages as $message)
                <div class="message-wrapper {{ $message->user->realname == auth()->user()->realname ? 'message-right' : 'message-left' }}">
                    <div class="card">
                        <div class="card-header">
                            <strong>{{ $message->user->realname }}</strong> - {{ $message->created_at->format('d M Y, H:i') }}
                        </div>
                        <div class="card-body">
                            @if($message->message)
                                <p>{{ $message->message }}</p>
                            @endif
            
                            @if($message->image_url)
                                <div class="message-image mt-2">
                                    <img src="{{ asset($message->image_url) }}" alt="Attached Image" class="img-fluid rounded">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    
        @if($ticket->status != 'closed')
            <h4 class="mt-4">添加回复</h4>
            <form id="add-reply-form" action="{{ route('tickets.message.store', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <textarea name="message" class="form-control" rows="4" placeholder="请输入您的回复..."></textarea>
                </div>
                <div class="form-group mt-2">
                    <label for="image">附加图片（可选）：</label>
                    <input type="file" name="image" id="image" class="form-control-file" accept="image/*">
                </div>
                <p class="text-muted">请输入一条消息或上传一张图片。</p>
                <button type="submit" class="btn btn-success mt-2">发送回复</button>
            </form>
        @else
            <p class="text-danger mt-4">此工单已关闭，无法回复。</p>
        @endif

    </div>


    <x-slot:footerFiles>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        <script src="{{ asset('plugins/jquery-ui/jquery-ui.min.js') }}"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
        <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.12.1/echo.iife.js"></script>

        @vite(['resources/assets/js/custom.js'])
        <script src="{{ asset('js/ticket.js') }}"></script>
        @vite(['resources/assets/js/apps/contact.js'])

        <script>
            var currentTicketId = {{ $ticket->id }};
            var currentUserRealName = "{{ auth()->user()->realname }}";
            console.log("Current Ticket ID:", currentTicketId);
            console.log("Current User Real Name:", currentUserRealName);
            window.PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
            window.PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";
        </script>
    </x-slot>
    
</x-base-layout>
