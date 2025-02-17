<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        {{$title}}
    </x-slot>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/apps/chat.scss'])
        @vite(['resources/scss/dark/assets/apps/chat.scss'])
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->
    
    <style>
        .chat-box {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .chat-conversation-box {
            flex: 1;
            overflow-y: auto;
        }
        
        .bubble img.chat-image {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border-radius: 8px;
        }
        
        .bubble audio {
            display: block;
            margin-top: 10px;
        }
        
        .bubble .chat-doc {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            background-color: #f0f0f0;
            color: #007bff;
            text-decoration: none;
            border-radius: 5px;
        }
        
        .bubble .chat-doc:hover {
            background-color: #e0e0e0;
            text-decoration: underline;
        }
        
        .chat-system .chat-box .bubble.me {
            background-color: white;
        }
        
        .chat-system .chat-box .bubble.me:before {
            background-color: white;
        }
        
        .chat-footer {
            position: sticky; /* Ensures it stays at the bottom */
            bottom: 0;
            background-color: #fff;
            padding: 10px;
            z-index: 10;
            border-top: 1px solid #ddd;
            display: flex; /* Ensures row alignment */
            justify-content: space-between; /* Space between input and button */
            align-items: center;
            display: none;
        }
        
        .chat-footer .chat-input {
            flex: 1;
        }
        
        .chat-footer .chat-input .input-group {
            display: flex;
            align-items: center;
        }
        
        .chat-footer .chat-input .form-control {
            flex: 1; /* Takes up available space */
            margin-right: 10px; /* Space between input and button */
            border-radius: 20px; /* Optional for rounded input */
            padding: 10px; /* Adds spacing inside input */
        }
        
        .chat-footer .chat-input .btn {
            flex-shrink: 0; /* Prevents the button from shrinking */
            padding: 10px 20px;
            border-radius: 20px; /* Optional for rounded button */
            white-space: nowrap; /* Prevents text wrapping */
        }
        
        .chat-conversation-box-scroll {
            padding-bottom: 60px; /* Adjust this value as needed */
        }

    </style>

    <div class="chat-section layout-top-spacing">
        <div class="row">
            <div class="col-xl-12 col-lg-12 col-md-12">
                <div class="chat-system">
                    <div class="hamburger">
                        <!-- Hamburger Icon -->
                    </div>
                    <div class="user-list-box">
                        <div class="search">
                            <input type="text" class="form-control" placeholder="Search User" />
                        </div>
                        <button id="reset-search" class="btn btn-primary" style="margin-left:15px">Reset</button>

                        <div class="people">
                            @foreach($conversations as $conversation)
                                @if($conversation->user)
                                    <div class="person"
                                         data-chat="conversation-{{ $conversation->id }}"
                                         data-referral-link="{{ $conversation->user->referral_link }}"
                                         data-user-id="{{ $conversation->user->id }}">
                                        <div class="user-info">
                                            <div class="f-head">
                                                {{-- Optional: User Avatar --}}
                                            </div>
                                            <div class="f-body">
                                                <div class="meta-info">
                                                    <span class="user-name">{{ $conversation->other_user_name }}</span>
                                                    <span class="user-meta-time">
                                                        {{ $conversation->last_message_time ? \Carbon\Carbon::parse($conversation->last_message_time)->diffForHumans() : 'No messages yet' }}
                                                    </span>
                                                </div>
                                                <span class="preview">
                                                    {{ $conversation->last_message ?? 'No messages yet' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach


                        </div>
                    </div>
                    <div class="chat-box" style="background-image: url({{Vite::asset('resources/images/bg.png')}}); overflow: auto;">
                        <div class="chat-not-selected">
                            <p>Click User To Chat</p>
                        </div>
                        <div class="chat-box-inner" style="min-height:100%">
                            <div class="chat-meta-user">
                                <div class="current-chat-user-name">
                                    <span class="name"></span>
                                </div>
                            </div>
                            <div class="chat-conversation-box" style="flex: 1;overflow-y: auto; max-height: calc(100vh - 200px); padding-left: 20px; padding-right: 20px; padding-top:10px">
                                <div class="chat-conversation-box-scroll">
                                    <!-- Messages will be dynamically loaded here -->
                                </div>
                            </div>
                        </div>
                        <div class="chat-footer">
                            <div class="chat-input">
                                <form id="chat-form" class="chat-form" action="javascript:void(0);">
                                    <div class="input-group">
                                        <input type="text" id="message-input" class="form-control" placeholder="Type a message" />
                                        <button type="submit" class="btn btn-primary" id="send-button">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
    <script src="{{asset('plugins/global/vendors.min.js')}}"></script>
    
    <script>
        
        let debounceTimeout;
        let pollingInterval = null;
        let lastMessageId = 0;

        // Function to fetch new messages
        function fetchNewMessages(conversationId) {
            $.ajax({
                url: `/app/conversations/${conversationId}/messages`,
                type: 'GET',
                success: function (response) {
                    const messagesContainer = $('.chat-conversation-box-scroll');
                    const newMessages = response.messages.filter(message => message.id > lastMessageId);
        
                    newMessages.forEach((message) => {
                        const bubbleClass = message.user_id === {{ Auth::id() }} ? 'me' : 'you';
                        let messageHtml = `<div class="bubble ${bubbleClass}">`;
        
                        if (message.message) {
                            messageHtml += `<p>${message.message}</p>`;
                        }
                        if (message.image_url) {
                            messageHtml += `<img src="/storage/${message.image_url}" alt="Image" class="chat-image">`;
                        }
                        if (message.audio_url) {
                            messageHtml += `<audio controls>
                                                <source src="/storage/${message.audio_url}" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>`;
                        }
                        if (message.doc_url) {
                            messageHtml += `<a href="/storage/${message.doc_url}" target="_blank" class="chat-doc">View Document</a>`;
                        }
        
                        messageHtml += `<span class="timestamp" style="font-size: 10px; color: grey;">
                                            ${new Date(message.created_at).toLocaleString()}
                                        </span></div>`;
        
                        messagesContainer.append(messageHtml);
        
                        // Update the lastMessageId
                        if (message.id > lastMessageId) {
                            lastMessageId = message.id;
                        }
                    });
        
                    if (newMessages.length > 0) {
                        scrollToBottom();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Failed to fetch new messages:', error);
                },
            });
        }
        
        // When a conversation is selected
        $('.user-list-box .person').on('click', function () {
            const findChat = $(this).attr('data-chat');
            const conversationId = findChat.split('-')[1];
        
            currentConversationId = conversationId; // Set active conversation ID
        
            // Add 'active' class to the clicked person, remove from others
            $('.user-list-box .person').removeClass('active');
            $(this).addClass('active');
        
            // AJAX request to load messages
            $.ajax({
                url: `/app/conversations/${conversationId}/messages`,
                type: 'GET',
                success: function (response) {
                    const messagesContainer = $('.chat-conversation-box-scroll');
                    messagesContainer.empty(); // Clear existing messages
        
                    response.messages.forEach((message) => {
                        const bubbleClass = message.user_id === {{ Auth::id() }} ? 'me' : 'you';
                        let messageHtml = `<div class="bubble ${bubbleClass}">`;
        
                        if (message.message) {
                            messageHtml += `<p>${message.message}</p>`;
                        }
                        if (message.image_url) {
                            messageHtml += `<img src="/storage/${message.image_url}" alt="Image" class="chat-image">`;
                        }
                        if (message.audio_url) {
                            messageHtml += `<audio controls>
                                                <source src="/storage/${message.audio_url}" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>`;
                        }
                        if (message.doc_url) {
                            messageHtml += `<a href="/storage/${message.doc_url}" target="_blank" class="chat-doc">View Document</a>`;
                        }
        
                        messageHtml += `<span class="timestamp" style="font-size: 10px; color: grey;">
                                            ${new Date(message.created_at).toLocaleString()}
                                        </span></div>`;
        
                        messagesContainer.append(messageHtml);
        
                        // Update lastMessageId
                        if (message.id > lastMessageId) {
                            lastMessageId = message.id;
                        }
                    });
        
                    // Scroll to the bottom after loading messages
                    scrollToBottom();
        
                    // Start polling for new messages
                    if (pollingInterval) {
                        clearInterval(pollingInterval);
                    }
                    pollingInterval = setInterval(function() {
                        fetchNewMessages(conversationId);
                    }, 5000); // Poll every 5 seconds
                },
                error: function (xhr, status, error) {
                    console.error('Failed to fetch messages:', error);
                },
            });
        
            // Update chat header with selected user's name
            const personName = $(this).find('.user-name').text();
            $('.current-chat-user-name .name').text(personName);
        
            // Show chat content
            $('.chat-not-selected').hide();
            $('.chat-box-inner').show();
            $('.chat-footer').show(); // Show the chat footer
        });
    
        function scrollToBottom() {
            const messagesContainer = document.querySelector('.chat-conversation-box-scroll');
            if (messagesContainer) {
                const lastMessage = messagesContainer.lastElementChild;
                if (lastMessage) {
                    lastMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }
            }
        }

        // Attach event listener to the chat form submission
        $('#chat-form').on('submit', function (event) {
            event.preventDefault();
        
            const chatInput = $('#message-input');
            const chatMessageValue = chatInput.val().trim();
            const activeChat = $('.user-list-box .person.active').data('chat')?.split('-')[1]; // Get active chat ID
        
            if (chatMessageValue === '' || !activeChat) return; // Skip if input is empty or no chat selected
        
            $.ajax({
                url: '/app/send-message',
                type: 'POST',
                data: {
                    message: chatMessageValue,
                    conversation_id: activeChat,
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    // Append the new message to the chat
                    const messagesContainer = $('.chat-conversation-box-scroll');
                    const messageHtml = `
                        <div class="bubble me">
                            <p>${response.message}</p>
                            <span class="timestamp" style="font-size: 10px; color: grey;">
                                ${new Date(response.created_at).toLocaleString()}
                            </span>
                        </div>`;
                    messagesContainer.append(messageHtml);
        
                    // Clear the input and scroll to bottom
                    chatInput.val('');
                    scrollToBottom();
                },
                error: function (xhr, status, error) {
                    console.error('Message failed to send:', error);
                    alert('Failed to send message. Please try again.');
                },
            });
        });

    
        // Toggle user list visibility for mobile view
        $('.hamburger, .chat-system .chat-box .chat-not-selected p').on('click', function() {
            $(this).closest('.chat-system').find('.user-list-box').toggleClass('user-list-box-show');
        });
    
        // Initialize Perfect Scrollbars
        new PerfectScrollbar('.people', { suppressScrollX: true });
    
        // Scroll to bottom of chat box on page load
        document.addEventListener("DOMContentLoaded", function() {
            scrollToBottom();
        });
    
        // Hide chat footer on initial load
        $(document).ready(function() {
            $('.chat-footer').hide();
        });
        
        // Reset button functionality
        $('#reset-search').on('click', function() {
            const searchInput = $('.search > input');
            searchInput.val(''); // Clear the search input
            $('.people .person').show(); // Show all results
        });
        
        $('.search > input').on('keyup', function () {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const searchText = $(this).val().toLowerCase();
                console.log(`Search text: ${searchText}`); // Log the current search input
        
                $('.people .person:visible').each(function () {
                    const name = $(this).find('.user-name').text().toLowerCase();
                    console.log(`Person Name: ${name}`); // Log the person's name
        
                    const isMatch = name.includes(searchText);
                    console.log(`Is Match: ${isMatch}`); // Log whether the name matches the search
        
                    $(this).toggle(isMatch); // Show or hide based on the match
                });
            }, 300);
        });


    </script>
</x-slot:footerFiles>

</x-base-layout>
