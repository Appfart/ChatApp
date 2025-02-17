// Debounced search function for better performance
let debounceTimeout;
$('.search > input').on('keyup', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        const searchText = $(this).val().toLowerCase();
        $('.people .person').each(function() {
            const isMatch = $(this).text().toLowerCase().includes(searchText);
            $(this).toggle(isMatch);
        });
    }, 300);
});

// Chat selection logic with improved class handling
$('.user-list-box .person').on('click', function () {
    console.log("User clicked a conversation."); // Debug log

    const findChat = $(this).attr('data-chat');
    console.log("Conversation ID:", findChat); // Check if conversation ID is correct

    const personName = $(this).find('.user-name').text();
    console.log("User Name:", personName); // Debug user name

    // AJAX request to load messages
    const conversationId = findChat.split('-')[1];
    $.ajax({
        url: `/app/conversations/${conversationId}/messages`,
        type: 'GET',
        success: function (response) {
            console.log("Messages fetched successfully:", response); // Log the fetched messages

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
            });

            scrollToBottom();
        },
        error: function (xhr, status, error) {
            console.error("Failed to fetch messages:", error); // Log error details
        },
    });

    // Show chat content, hide the placeholder
    $('.chat-not-selected').hide();
    $('.chat-box-inner').show();
    $('.current-chat-user-name .name').text(personName);
});


function scrollToBottom() {
    const chatBox = document.querySelector('.chat-conversation-box-scroll');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

// Attach event listener to the chat form submission
$('#chat-form').on('submit', function(event) {
    event.preventDefault();

    const chatInput = $('#message-input');
    const chatMessageValue = chatInput.val().trim();
    const activeChat = $('.active-chat').data('chat')?.split('-')[1];

    console.log("Attempting to send message:", chatMessageValue, "to conversation ID:", activeChat);

    if (chatMessageValue === '' || !activeChat) return;

    $.ajax({
        url: '/app/send-message',
        type: 'POST',
        data: {
            message: chatMessageValue,
            conversation_id: activeChat,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log("Message sent successfully:", response);

            const messageHtml = `<div class="bubble me">
                                    <p>${response.message}</p>
                                    <span class="timestamp" style="font-size: 10px; color: grey;">
                                        ${new Date(response.created_at).toLocaleString()}
                                    </span>
                                </div>`;
            $('.active-chat').append(messageHtml);

            // Clear the input and scroll to the latest message
            chatInput.val('');
            scrollToBottom(); // Call the scroll function after message is appended
        },
        error: function(xhr, status, error) {
            console.error("Message failed to send:", error);
        }
    });
});

// Toggle user list visibility for mobile view
$('.hamburger, .chat-system .chat-box .chat-not-selected p').on('click', function() {
    $(this).closest('.chat-system').find('.user-list-box').toggleClass('user-list-box-show');
});

// Initialize Perfect Scrollbars
new PerfectScrollbar('.people', { suppressScrollX: true });
