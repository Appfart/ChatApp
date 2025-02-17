{{-- /home/yellow/chat/resources/views/pages/app/tickets/index.blade.php --}}
<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        客服单号
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/components/modal.scss'])
        @vite(['resources/scss/light/assets/apps/contacts.scss'])

        @vite(['resources/scss/dark/assets/components/modal.scss'])
        @vite(['resources/scss/dark/assets/apps/contacts.scss'])

        <!-- CSRF Token Meta Tag -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <style>
            .status-open {
                color: green;
                font-weight: bold;
            }
            .status-pending {
                color: orange;
                font-weight: bold;
            }
            .status-closed {
                color: red;
                font-weight: bold;
            }
            .status-unknown {
                color: gray;
                font-weight: bold;
            }
        </style>
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <div class="container mt-4">
        <h1>客服工单</h1>
        <!--<a href="{{ route('tickets.create') }}" class="btn btn-primary mb-3">创建新票据</a>-->
        <table class="table table-bordered" id="tickets-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>主题</th>
                    <th>状态</th>
                    <th>创建者</th>
                    <th>最新消息</th>
                    <th>最后更新</th>
                    <th>发送者</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $ticket)
                    <tr id="ticket-row-{{ $ticket->id }}" 
                        data-ticket-id="{{ $ticket->id }}" 
                        data-updated-at="{{ $ticket->updated_at->toIso8601String() }}">
                        <td>{{ $ticket->id }}</td>
                        <td>{{ $ticket->subject }}</td>
                        <td class="ticket-status {{ 'status-' . strtolower($ticket->status) }}">
                            {{ ucfirst($ticket->status) }}
                        </td>
                        <td>{{ $ticket->user->realname }}</td>
                        <td class="latest-message">
                            @if($ticket->latestMessage)
                                {{ \Illuminate\Support\Str::limit($ticket->latestMessage->message, 50) }}
                            @else
                                尚无消息。
                            @endif
                        </td>
                        <td>{{ $ticket->updated_at->diffForHumans() }}</td>
                        <td class="message-sender">
                            @if($ticket->latestMessage)
                                {{ $ticket->latestMessage->user->realname }}
                            @else
                                无
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('tickets.show', $ticket->id) }}" class="btn btn-info btn-sm">查看</a>
                            @if(Auth::user()->role != 'user' && $ticket->status != 'closed')
                            <form action="{{ route('tickets.close', $ticket->id) }}" method="POST" class="close-ticket-form" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">关闭</button>
                            </form>
                            @else
                                <button type="submit" class="btn btn-warning btn-sm" disabled>关闭</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-slot:footerFiles>
        <!-- Load jQuery first -->
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
            var currentUserRealName = "{{ auth()->user()->realname }}";
            window.PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
            window.PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";
        </script>
    </x-slot>
    
</x-base-layout>
