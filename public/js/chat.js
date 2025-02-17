window.updateMarquee = function(announcement) {
    const marquee = $('#announcement-marquee');
    if (announcement && announcement.trim() !== '') {
        marquee.show();
        marquee.html(`<span>${announcement}</span>`);
    } else {
        marquee.hide();
    }
};

$(document).ready(function () {
    // Ensure that Echo and Pusher are loaded
    if (typeof Echo === 'undefined' || typeof Pusher === 'undefined') {
        console.error('Echo or Pusher not found. Ensure that the CDN scripts are correctly included.');
        return;
    }

    // Initialize Laravel Echo
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: 'c6aba6b00c27cb34c21b',
        cluster: 'ap1',
        forceTLS: true,
        encrypted: true,
        // If using a custom host, port, or scheme, add them here
        // host: 'your-pusher-host',
        // port: 443,
        // scheme: 'https',
    });

    // Set up AJAX to include CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    let debounceTimeout;
    let pollingInterval = null;
    let lastMessageId = 0;
    let currentConversationId = null;
    let refreshChatsInterval = null;
    
    // Audio Recording Variables
    let mediaRecorder;
    let audioChunks = [];
    
    const userId = Number(window.chatConfig.userId);
    console.log(`Subscribing to channel: public-user.${userId}`);
    
    window.Echo.channel(`public-user.${userId}`)
        .listen('.MessageSent', (e) => {
            console.log('Received new personal msg event:', e);
            handleIncomingMessage(e);
            debouncedRefreshChats(); // Use debounced refresh
        })
        
        .listen('.GroupMessageSent', (e) => {
            console.log('Received new group msg event:', e);
            handleIncomingMessage(e);
            debouncedRefreshChats(); // Use debounced refresh
        })
        
        .listen('.NewGroupCreated', (e) => {
            console.log('Received NewGroupCreated event:', e);
            GroupUpdate(e.grpchat, e.members);
            debouncedRefreshChats(); // Use debounced refresh
        })
        
        .listen('.NewFriendRequest', (e) => {
            console.log('New friend request received:', e);
            updateFriendRequestCount();
        })
        
        .error((error) => {
            console.error('Error listening to public-user events:', error);
        });
    
    window.Echo.channel(`group.${userId}`)
        .listen('.GroupMemberUpdated', (e) => {
            console.log('Group members updated:', e.updatedMembers);
            GroupUpdate(e.grpchat, e.updatedMembers);
            debouncedRefreshChats(); // Use debounced refresh
        })
        .error((error) => {
            console.error('Error listening to group events:', error);
        });
    
    // Reply Button Event Handler
    $(document).on('click', '.reply-button', function (event) {
        // Prevent the event from bubbling up to the document click listener
        event.stopPropagation();
    
        // Use `selectedBubble` if set, otherwise use the clicked button's context
        let bubble = selectedBubble ? $(selectedBubble) : $(this).closest('.bubble');
    
        if (!bubble.length) {
            console.warn('No bubble found for reply action.');
            return;
        }
    
        const messageId = bubble.data('message-id');
        const isGroupChat = bubble.data('is-group-chat') === true || bubble.data('is-group-chat') === 'true';
        const senderName = bubble.data('sender-name') || 'Unknown';
    
        // Determine message text based on available content
        let messageText = bubble.find('p.preserve-whitespace').text();
    
        if (!messageText) {
            if (bubble.find('img.chat-image').length) {
                messageText = '图片信息'; // Image Message
            } else if (bubble.find('a.chat-document').length) {
                messageText = '文档信息'; // Document Message
            } else if (bubble.find('video.chat-video').length) {
                messageText = '视频信息'; // Video Message
            } else if (bubble.find('audio').length) {
                messageText = '音频信息'; // Audio Message
            } else {
                messageText = '未知信息'; // Unknown Message
            }
        }
    
        $('#reply-to-id').val(messageId);
        $('#replying-to strong').text(`${senderName}: ${messageText}`);
        $('#reply-context-container').show();
    
        // Focus on the chat input
        const chatInput = $('#message-input');
        chatInput.focus();
    
        console.log("Reply button clicked, messageId:", messageId, "isGroupChat:", isGroupChat);
    });
    
    // Recall Button Event Handler
    $(document).on('click', '.recall-button', function (event) {
        // Prevent the event from bubbling up to the document click listener
        event.stopPropagation();
    
        // Use `selectedBubble` if set, otherwise use the clicked button's context
        let bubble = selectedBubble ? $(selectedBubble) : $(this).closest('.bubble');
    
        if (!bubble.length) {
            console.warn('No bubble found for recall action.');
            return;
        }
    
        const messageId = bubble.data('message-id');
        const isGroupChat = bubble.data('is-group-chat') === true || bubble.data('is-group-chat') === 'true';
    
        console.log("Recall button clicked, messageId:", messageId, "isGroupChat:", isGroupChat);
    
        // Confirm recall action
        if (!confirm('您确定要撤回此消息吗？')) {
            return;
        }
    
        // Prepare data
        const requestData = {
            message_id: messageId,
            is_group_chat: isGroupChat ? 1 : 0,
            impersonation_token: window.chatConfig.impersonationToken
        };
    
        console.log("发送数据:", requestData); // Debugging
    
        // Send AJAX request
        $.ajax({
            url: '/robot/recall',
            type: 'POST',
            data: requestData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // Include CSRF token
            },
            success: function (response) {
                console.log("撤回响应:", response);
                debouncedRefreshChatList();
    
                // Update recalled message UI
                const bubbleSelector = `.bubble[data-message-id="${messageId}"]`;
                const bubbleElement = $(bubbleSelector);
    
                // Extract the timestamp
                const timestamp = bubbleElement.find('.timestamp').text();
    
                // Replace the entire bubble content with the recall message and timestamp
                bubbleElement.html(`
                    <div class="recall-message">
                        【信息已经撤回】
                    </div>
                    <span class="timestamp">${timestamp}</span>
                `);
    
                // Optionally, add styling to the recall message
                bubbleElement.find('.recall-message').css({
                    'color': '#515365',
                });
    
                debouncedRefreshChatList();
                alert('消息已成功撤回。');
            },
            error: function (xhr, status, error) {
                const errorMsg = xhr.responseJSON?.error || '未知错误';
                alert('撤回消息失败: ' + errorMsg);
                console.error("撤回消息错误:", xhr, status, error);
            }
        });
    });
    
    $(document).on('click', '#cancel-reply', function () {
        $('#reply-to-id').val('');
        $('#replying-to strong').text('');
        $('#reply-context-container').hide();
    });
    
    $(document).on('click', '.remove-conversation', function () {
        // Get the chat ID and type directly from the button
        const chatId = $(this).data('chat-id');
        const chatType = 'conversation';
    
        if (chatType !== 'conversation') {
            alert('只能移除个人对话。'); // "Only personal conversations can be removed."
            return;
        }
    
        // Show the confirmation modal
        $('#removeConversationModal')
            .data('chat-id', chatId)
            .data('is-group-chat', 0) // 0 indicates personal conversation
            .modal('show');
    });
    
    $(document).on('click', '.confirm-remove', function () {
        const chatId = $('#removeConversationModal').data('chat-id');
        const isGroupChat = $('#removeConversationModal').data('is-group-chat');
    
        // Since we are restricting to personal conversations, isGroupChat should be 0
        // But adding the condition for future-proofing
        const ajaxUrl = isGroupChat ? `/grpchat/${chatId}/remove` : `/conversation/${chatId}/remove`;
    
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                is_group_chat: isGroupChat, // 0 for personal conversations
                _token: window.chatConfig.csrfToken,
                impersonation_token: window.chatConfig.impersonationToken
            },
            success: function (response) {
                $('#removeConversationModal').modal('hide');
                alert(response.message || '对话已成功移除。'); // "Conversation successfully removed."
    
                // Optionally, reload the page or remove the conversation from the UI
                location.reload();
            },
            error: function (xhr) {
                $('#removeConversationModal').modal('hide');
                alert(xhr.responseJSON.error || '移除对话时发生错误，请稍后重试。'); // "An error occurred while removing the conversation. Please try again later."
            },
        });
    });
    
    $(document).on('click', '.btn-secondary, .close', function () {
        $('#removeConversationModal').modal('hide');
    });
    
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function refreshChats() {
        console.log("Debounced Refresh Chats Called");
        refreshChatList();
        debouncedRefreshChats();
    }
    
    const debouncedRefreshChatList = debounce(refreshChats, 500);
    
    function refreshChatList() {
        console.log("Starting chat list refresh...");
        $.ajax({
            url: `/robot/sorted-chats?impersonation_token=${window.chatConfig.impersonationToken}`,
            type: 'GET',
            success: function (response) {
                if (response.sorted_chats && Array.isArray(response.sorted_chats)) {
                    const sortedChats = response.sorted_chats;
                    const chatListContainer = $('.people');
                    const activeChatElement = chatListContainer.find('.person.active');
                    const activeChatType = activeChatElement.data('chat-type');
                    const activeChatId = activeChatElement.data('chat-id');
    
                    // Detach all chat elements
                    const detachedChats = {};
                    chatListContainer.find('.person').each(function () {
                        const chatType = $(this).data('chat-type');
                        const chatId = $(this).data('chat-id');
                        const key = `${chatType}-${chatId}`;
                        detachedChats[key] = $(this).detach();
                    });
    
                    // Re-append chats in sorted order
                    sortedChats.forEach(function (chat) {
                        const key = `${chat.type}-${chat.id}`;
                        if (detachedChats[key]) {
                            const $person = detachedChats[key];
                            
                            // Use latest_message_content instead of latest_message.message
                            const previewText = chat.latest_message_content || '有新消息';
                            const timeText = chat.latest_message 
                                ? new Date(chat.latest_message.created_at).toLocaleString() 
                                : '暂无消息';
                            
                            $person.find('.user-meta-time').text(timeText);
                            $person.find('.preview').text(previewText);
    
                            const unreadCount = chat.unread_count || 0;
    
                            if (unreadCount > 0) {
                                $person.find('.unread-count').show().text(unreadCount);
                            } else {
                                $person.find('.unread-count').hide().text('');
                            }
    
                            chatListContainer.append($person);
                        }
                    });
    
                    // Re-activate the previously active chat
                    if (activeChatType && activeChatId) {
                        chatListContainer.find(`.person[data-chat-type="${activeChatType}"][data-chat-id="${activeChatId}"]`).addClass('active');
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Failed to refresh chat list:', error);
            }
        });
    }

    function debouncedRefreshChats() {
        console.log("Starting chat order refresh...");
        const impersonationToken = window.chatConfig.impersonationToken;
    
        const activeChatElement = $('.people .person.active');
        let activeChatType = null;
        let activeChatId = null;
        if (activeChatElement.length) {
            activeChatType = activeChatElement.data('chat-type');
            activeChatId = activeChatElement.data('chat-id');
        }
    
        $.ajax({
            url: '/robot/sorted-chats',
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            success: function (response) {
                if (response.sorted_chats && Array.isArray(response.sorted_chats)) {
                    const sortedChats = response.sorted_chats;
                    const chatListContainer = $('.people');
                    const persons = chatListContainer.find('.person');
    
                    const chatMap = {};
                    persons.each(function () {
                        const chatType = $(this).data('chat-type');
                        const chatId = $(this).data('chat-id');
                        const key = `${chatType}-${chatId}`;
                        chatMap[key] = $(this).detach();
                    });
    
                    sortedChats.forEach(function (chat) {
                        const key = `${chat.type}-${chat.id}`;
                        if (chatMap[key]) {
                            const $person = chatMap[key];
                            // Use latest_message_content instead of latest_message.message
                            const previewText = chat.latest_message_content || '有新消息';
                            const timeText = chat.latest_message 
                            ? new Date(chat.latest_message.created_at).toLocaleString('en-GB', {
                                day: '2-digit',
                                month: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit',
                            }).replace(',', '')
                            : '暂无消息';

                            
                            $person.find('.user-meta-time').text(timeText);
                            $person.find('.preview').text(previewText);
    
                            const unreadCount = chat.unread_count || 0;
                            if (unreadCount > 0) {
                                $person.find('.unread-count').show().text(unreadCount);
                            } else {
                                $person.find('.unread-count').hide().text('');
                            }
    
                            chatListContainer.append($person);
                        }
                    });
    
                    // Re-activate the previously active chat
                    if (activeChatType && activeChatId) {
                        chatListContainer.find(`.person[data-chat-type="${activeChatType}"][data-chat-id="${activeChatId}"]`).addClass('active');
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Failed to refresh chat order:', error);
            }
        });
    }
    
    function scrollToBottom() {
        const messagesContainer = document.querySelector('.chat-conversation-box-scroll');
        if (messagesContainer) {
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 50);
        }
    }

    function escapeHTML(str) {
        // Convert the input to a string if it's not already
        if (typeof str !== 'string') {
            str = String(str);
        }
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Send Message
    $('#chat-form').on('submit', function (event) {
        event.preventDefault();
    
        const chatInput = $('#message-input');
        const chatMessageValue = chatInput.val().trim();
        const activeChat = currentConversationId;
        const isGroupChat = $('.person.active').data('chat-type') === 'grpchat';
        const replyToId = $('#reply-to-id').val();
    
        if (chatMessageValue === '' || !activeChat) {
            return;
        }
    
        $.ajax({
            url: `/robot/send-message?impersonation_token=${window.chatConfig.impersonationToken}`,
            type: 'POST',
            data: {
                message: chatMessageValue,
                chat_id: activeChat,
                is_group_chat: isGroupChat ? 1 : 0,
                reply_to_id: replyToId,
                _token: window.chatConfig.csrfToken,
            },
            success: function (response) {
                console.log('Send Message Response:', response);
                const messagesContainer = $('.chat-conversation-box-scroll');
                const avatarUrl = `${window.location.origin}/storage/${response.sender_avatar}`;
                let messageContent = '';
                
                const readTimestamp = response.read_timestamp 
                    ? new Date(response.read_timestamp) 
                    : null;
                    
                console.log("Read Timestamp:", readTimestamp);

                // Handle different message types
                if (response.image_url) {
                    const imageUrl = `${window.location.origin}/storage/${response.image_url}`;
                    messageContent += `<img src="${imageUrl}" alt="Image" class="chat-image"
                                        data-message-id="${response.id}" 
                                        data-is-group-chat="${isGroupChat}" 
                                        data-sender-name="${response.sender_name}">`;
                } else if (response.doc_url) {
                    const docUrl = `${window.location.origin}/storage/${response.doc_url}`;
                    const fileName = extractFileName(response.doc_url);
                    messageContent += `<a href="${docUrl}" target="_blank" class="chat-document"
                                        data-message-id="${response.id}" 
                                        data-is-group-chat="${isGroupChat}" 
                                        data-sender-name="${response.sender_name}">
                                            <i class="fas fa-file-alt"></i> ${fileName}
                                       </a>`;
                } else if (response.audio_url) {
                    const audioUrl = `${window.location.origin}/storage/${response.audio_url}`;
                    messageContent += `<audio controls src="${audioUrl}" 
                                        data-message-id="${response.id}" 
                                        data-is-group-chat="${isGroupChat}" 
                                        data-sender-name="${response.sender_name}"></audio>`;
                } else {
                    messageContent += `<p class="preserve-whitespace"
                                        data-message-id="${response.id}" 
                                        data-is-group-chat="${isGroupChat}" 
                                        data-sender-name="${response.sender_name}">
                                            ${escapeHTML(response.message || '')}
                                        </p>`;
                }
                
                // Optional: Reply context
                let replyContext = '';
                if (response.reply_to_id) {
                    const replyUserName = escapeHTML(response.reply_to_user_name || 'Unknown');
                    const replyMessage = escapeHTML(response.reply_to_message || '');
                    replyContext = `
                        <div class="reply-context">
                            <strong>${replyUserName}:</strong> ${replyMessage}
                        </div>
                    `;
                }
                
                let isRead = false;
                if (readTimestamp) {
                    const messageTime = new Date(response.created_at);
                    isRead = messageTime.getTime() <= readTimestamp.getTime();
                }
                
                // Construct the message HTML
                const messageHtml = `
                    <div class="message-container me">
                        <span class="timestamp">${new Date(response.created_at).toLocaleString('en-GB', {
                            day: '2-digit',
                            month: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                        }).replace(',', '')}</span>
                        <div class="message-row">
                            <div class="bubble" data-message-id="${response.id}" data-is-group-chat="${isGroupChat}" data-sender-name="${response.sender_name}">
                                ${replyContext}
                                ${messageContent}
                            </div>
                            <div class="read-status ${isRead ? 'read' : ''}"></div>
                            <img class="avatar me" src="${avatarUrl}" alt="${escapeHTML(response.sender_name)}'s avatar" />
                        </div>
                    </div>
                `;

            
                // Append to chat container and scroll to bottom
                messagesContainer.append(messageHtml);
                scrollToBottom();
            
                // Update lastMessageId
                if (response.id > lastMessageId) {
                    lastMessageId = response.id;
                }
            
                // Clear input and reply context
                $('#message-input').val('');
                $('#reply-to-id').val('');
                $('#replying-to strong').text('');
                $('#reply-context-container').hide();
            },
    
            error: function (xhr, status, error) {
                console.error('服务器问题，请稍后:', error);
    
                let errorMessage = '发送消息时出现问题，请稍后再试。';
    
                if (xhr.responseJSON) {
                    if (xhr.status === 422) {
                        // Handle Validation Errors
                        const validationErrors = xhr.responseJSON.details;
                        const errorList = Object.values(validationErrors).flat().join('\n');
                        errorMessage = `验证错误:\n${errorList}`;
                    } else if (xhr.status === 403) {
                        // Handle Authorization Errors
                        errorMessage = `权限错误: ${escapeHTML(xhr.responseJSON.error)}`;
                    } else if (xhr.responseJSON.error) {
                        // Handle Other Errors with a specific message
                        errorMessage = `错误: ${escapeHTML(xhr.responseJSON.error)}`;
                    }
                }
    
                // Display the error message using alert
                alert(errorMessage);
    
                // Re-enable the submit button and reset its text
                $('#send-button').prop('disabled', false).text('发送');
            },
        });
    });

    $('#message-input').on('keydown', function(event) {
        if (event.key === 'Enter') {
            if (!event.shiftKey) {
                event.preventDefault();
                $('#chat-form').submit();
            }
            // If Shift+Enter, allow newline
        }
    });
    
    function GroupUpdate(grpchat, members) {
        //console.log('Handling GroupMemberUpdated event for userId:', userId, 'members:', members);
        
        // Convert members array elements to numbers for consistency
        const membersNumbers = members.map(id => Number(id));
        //console.log('Converted members to numbers:', membersNumbers);
        
        // Ensure userId is a number and check if it's included in the membersNumbers array
        if (!membersNumbers.includes(userId)) {
            console.log('User is not a member of the group. Skipping.');
            return;
        }
    
        console.log('User is a member. Adding/updating group in chat list.');
    
        const chatListContainer = $('.people');
        const latestMessage = grpchat.latest_message || { message: '有新消息', created_at: grpchat.created_at };
    
        // Check if the group already exists in the chat list
        let groupElement = chatListContainer.find(`.person[data-chat-id="${grpchat.id}"]`);
        if (groupElement.length) {
            // Update existing group chat info if needed
            groupElement.find('.user-meta-time').text(latestMessage.created_at ? new Date(latestMessage.created_at).toLocaleString() : '有新消息');
            groupElement.find('.preview').text(latestMessage.message || '有新消息');
            if (grpchat.unread_count > 0) {
                groupElement.find('.unread-count').show().text(grpchat.unread_count);
            } else {
                groupElement.find('.unread-count').hide().text('');
            }
        } else {
            const isAdmin = grpchat.admins.includes(String(userId));
            const isNotOwner = userId !== Number(grpchat.owner);
    
            // Construct the new chat HTML with conditional buttons
            let chatHtml = `
                <div class="person"
                     data-chat-type="grpchat"
                     data-chat-id="${grpchat.id}">
                    <div class="user-info">
                        <div class="f-head">
                            ${grpchat.avatar ? `<img src="${window.location.origin}/storage/${grpchat.avatar}" alt="Group Avatar" class="avatar">` : `<div class="avatar-default"></div>`}
                        </div>
                        <div class="f-body" style="margin-left:5px">
                            <div class="meta-info">
                                <span class="user-name">${grpchat.chatname}</span>
                                <span class="user-meta-time">
                                    ${latestMessage.created_at ? new Date(latestMessage.created_at).toLocaleString() : '有新消息'}
                                </span>
                                ${grpchat.unread_count > 0 ? `<span class="unread-count badge bg-danger">${grpchat.unread_count}</span>` : `<span class="unread-count badge bg-danger" style="display:none;"></span>`}
                            </div>
                            <span class="preview">
                                ${latestMessage.message || '有新消息'}
                            </span>
                            ${isAdmin ? `
                                <div class="settings-icon" style="margin-top: 5px;">
                                    <a href="javascript:void(0)" class="open-settings" data-grpchat-id="${grpchat.id}" title="Group Settings">
                                        <i class="fas fa-cogs"></i>
                                    </a>
                                    <a href="javascript:void(0)" class="open-mute-settings" data-grpchat-id="${grpchat.id}" title="Mute Members">
                                        <i class="fas fa-volume-mute text-danger"></i>
                                    </a>
                                </div>
                            ` : ''}
                            ${isNotOwner ? `
                                <div class="quit-icon" style="margin-top: 5px;">
                                    <a href="javascript:void(0)" class="quit-group" data-grpchat-id="${grpchat.id}" title="Quit Group">
                                        <i class="fas fa-sign-out-alt text-warning"></i>
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            chatListContainer.prepend(chatHtml);
        }
    }

    $(document).on('click', '.quit-group', function () {
        const grpchatId = $(this).data('grpchat-id');
        const confirmation = confirm('您确定要退出该群组吗？');
    
        if (!confirmation) return;
    
        $.ajax({
            url: `/robot/grpchats/${grpchatId}/quit?impersonation_token=${window.chatConfig.impersonationToken}`, // 您的退出群组接口
            method: 'POST',
            data: {
                _token: window.chatConfig.csrfToken,
                impersonation_token: window.chatConfig.impersonationToken
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert('您已成功退出该群组。');
                    $(`.person[data-chat-id="${grpchatId}"]`).remove();
                } else if (response.error === 'Quitting this group chat is disabled.') {
                    alert('该群组不允许退出。');
                } else {
                    alert('退出群组失败，请重试。');
                }
            },
            error: function () {
                alert('发生错误，请稍后重试。');
            }
        });
    });

    //Show chat
    let messageLimit = 30;
    let messageOffset = 0;
    let isLoadingMessages = false;
    let allMessagesLoaded = false;
    let isGroupChat = false;
    let maxMsgId = 0;
    let chatType = null;
    
    // Function to fetch messages with pagination
    function fetchMessages(chatId, isGroupChatFlag, initialLoad = false) {
        if (isLoadingMessages || allMessagesLoaded) return;
        isLoadingMessages = true;
    
        let data = {
            impersonation_token: window.chatConfig.impersonationToken,
            limit: messageLimit,
            offset: messageOffset,
        };
    
        if (isGroupChatFlag) {
            data.is_group_chat = 1;
        }
    
        $.ajax({
            url: `/robot/conversations/${chatId}/messages`,
            type: 'GET',
            data: data,
            success: function (response) {
                console.log('ShowChat Response:', response);
                const messagesContainer = $('.chat-conversation-box-scroll');
    
                if (initialLoad) {
                    messagesContainer.empty();
                }
    
                if (response.messages.length < messageLimit) {
                    allMessagesLoaded = true;
                }
    
                let previousHeight = initialLoad ? 0 : messagesContainer[0].scrollHeight;
    
                // Initialize usersMap
                const usersMap = {};
                if (response.users && Array.isArray(response.users)) {
                    response.users.forEach(user => {
                        usersMap[user.id] = user;
                    });
                } else if (isGroupChatFlag) {
                    console.error("response.users is missing or not an array for group chat.");
                }
    
                response.messages.forEach((message) => {
                    const messageUserId = Number(message.user_id);
                    const messageUsername = message.user.realname !== "none" ? message.user.realname : message.user.name;
                    const baseUrl = "https://qmxk.cloud/storage/";
                    const defaultAvatarUrl = "https://qmxk.cloud/default-avatar.png";
                    const messageUserAvatar = message.user.avatar ? `${baseUrl}${message.user.avatar}` : defaultAvatarUrl;
                    const bubbleClass = messageUserId === userId ? 'me' : 'you';
    
                    const isRecalled = message.status === 0;
    
                    if (message.id > maxMsgId) {
                        maxMsgId = message.id;
                    }
    
                    let messageContent = '';
    
                    if (message.image_url) {
                        const imageUrl = `${window.location.origin}/storage/${message.image_url}`;
                        messageContent += `<img src="${imageUrl}" alt="Image" class="chat-image"
                                            data-message-id="${message.id}" 
                                            data-is-group-chat="${isGroupChatFlag}" 
                                            data-sender-name="${message.user.realname}">`;
                    }
    
                    if (message.doc_url) {
                        const docUrl = `${window.location.origin}/storage/${message.doc_url}`;
                        const fileName = extractFileName(message.doc_url);
                        messageContent += `<a href="${docUrl}" target="_blank" class="chat-document" 
                                            data-message-id="${message.id}" 
                                            data-is-group-chat="${isGroupChatFlag}" 
                                            data-sender-name="${message.user.realname}">
                                                <i class="fas fa-file-alt"></i> ${fileName}
                                           </a>`;
                    }
    
                    if (message.video_url) {
                        const videoUrl = `${window.location.origin}/storage/${message.video_url}`;
                        messageContent += `<video controls class="chat-video"
                                            data-message-id="${message.id}" 
                                            data-is-group-chat="${isGroupChatFlag}" 
                                            data-sender-name="${message.user.realname}">
                                                <source src="${videoUrl}" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>`;
                    }
    
                    if (message.audio_url) {
                        const audioUrl = `${window.location.origin}/storage/${message.audio_url}`;
                        messageContent += `<audio controls src="${audioUrl}" 
                                            data-message-id="${message.id}" 
                                            data-is-group-chat="${isGroupChatFlag}" 
                                            data-sender-name="${message.user.realname}"></audio>`;
                    }
    
                    if (message.message) {
                        message.tagged_users.forEach(taggedUser => {
                            const tagPattern = new RegExp('@' + taggedUser.name, 'g');
                            const tagSpan = `<span class="tagged-user" data-user-id="${taggedUser.id}">@${taggedUser.name}</span>`;
                            message.message = message.message.replace(tagPattern, tagSpan);
                        });
    
                        messageContent += `<p class="preserve-whitespace">${message.message}</p>`;
                    }
    
                    let replyContext = '';
                    
                    if (message.reply_to_id) {
                        replyContext = `
                            <div class="reply-context">
                                <strong>${message.reply_to_user_name || 'Unknown'}:</strong> ${message.reply_to_message || ''}
                            </div>
                        `;
                    }
    
                    // Format timestamp as d/m h:m
                    const formattedTimestamp = new Date(message.created_at).toLocaleString('en-GB', {
                        day: '2-digit',
                        month: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).replace(',', '');
                    
                    let isRead = false;
    
                    let messageHtml;
    
                    if (isGroupChatFlag && response.chat_info.type === "grpchat") {
                        const readTimestamps = response.read_timestamps || {};
    
                        // Get users who have read the message
                        const readers = Object.entries(readTimestamps)
                            .filter(([userId, readTimestamp]) => {
                                const readTime = new Date(readTimestamp);
                                const messageTime = new Date(message.created_at);
                                return readTime.getTime() >= messageTime.getTime();
                            })
                            .map(([userId]) => userId);
    
                        // Create a readable list of user names with avatars
                        const readerNamesHtml = readers
                            .map(readerId => {
                                const user = usersMap[readerId];
                                if (user) {
                                    const avatarUrl = user.avatar ? `${baseUrl}${user.avatar}` : defaultAvatarUrl;
                                    const displayName = user.realname !== "none" ? user.realname : user.name;
                                    return `
                                        <div class="reader">
                                            <img src="${avatarUrl}" alt="${displayName}'s avatar" class="reader-avatar" />
                                            <span class="reader-name">${displayName}</span>
                                        </div>
                                    `;
                                } else {
                                    return `<div class="reader">User ${readerId}</div>`;
                                }
                            })
                            .join("");
    
                        const readInfoHtml = `
                            <div class="read-status-info-wrapper">
                                <div class="read-status-info">
                                    <div class="readers-container">
                                        ${readerNamesHtml || ""}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        messageHtml = `
                            <div class="message-container ${bubbleClass}">
                                <span class="timestamp">${formattedTimestamp}</span>
                                <div class="message-row">
                                    ${bubbleClass === 'you' 
                                        ? `<img class="avatar you" src="${messageUserAvatar}" alt="${messageUsername}'s avatar" />` 
                                        : ''}
                                    <div class="bubble" data-message-id="${message.id}" data-is-group-chat="${isGroupChatFlag}" data-sender-name="${message.user.name}">
                                    <div class="sender-name">${message.user.name}</div>
                                        ${replyContext}
                                        ${messageContent}
                                        ${bubbleClass === 'me' ? readInfoHtml : ''}
                                    </div>
                                    ${bubbleClass === 'me' 
                                        ? `<img class="avatar me" src="${messageUserAvatar}" alt="${messageUsername}'s avatar" />` 
                                        : ''}
                                </div>
                            </div>
                        `;
    
                    } else {
    
                        if (response.chat_info.type === "conversation" && response.read_timestamp) {
                            const messageTime = new Date(message.created_at);
                            isRead = messageTime.getTime() <= new Date(response.read_timestamp).getTime();
                        }
    
                        messageHtml = `
                            <div class="message-container ${bubbleClass}">
                                <span class="timestamp">${formattedTimestamp}</span>
                                <div class="message-row">
                                    ${bubbleClass === 'you' ? `<img class="avatar you" src="${messageUserAvatar}" alt="${messageUsername}'s avatar" />` : ''}
                                    <div class="bubble" data-message-id="${message.id}" data-is-group-chat="${isGroupChatFlag}" data-sender-name="${message.user.name}">
                                        ${replyContext}
                                        ${messageContent}
                                    </div>
                                    <div class="read-status ${bubbleClass === 'me' ? (isRead ? 'read' : '') : ''}"></div>
                                    ${bubbleClass === 'me' ? `<img class="avatar me" src="${messageUserAvatar}" alt="${messageUsername}'s avatar" />` : ''}
                                </div>
                            </div>
                        `;
                    }
    
                    // Append or Prepend based on initial load
                    if (initialLoad) {
                        messagesContainer.append(messageHtml);
                    } else {
                        messagesContainer.prepend(messageHtml);
                    }
    
                    // Update Chat Header
                    updateChatHeader(response);
    
                    // Update Remark Button
                    const chatInfo = response.chat_info;
                    $('#remark-btn').attr('data-user', JSON.stringify({
                        id: chatInfo.id,
                        name: chatInfo.realname,
                        nickname: chatInfo.nickname,
                        age: chatInfo.age,
                    }));
                });
    
                lastMessageId = maxMsgId;
    
                if (!initialLoad) {
                    // Maintain scroll position after prepending
                    let newHeight = messagesContainer[0].scrollHeight;
                    messagesContainer.scrollTop(newHeight - previousHeight);
                } else {
                    scrollToBottom();
                }
    
                if (chatId !== currentConversationId) {
                    console.log("currentConversationId...");
                    debouncedRefreshChats();
                }
    
                const personElement = $(`.person[data-chat-type="${chatType}"][data-chat-id="${chatId}"]`);
                personElement.find('.unread-count').hide().text('');
    
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                }
                pollingInterval = setInterval(function () {
                    // fetchNewMessages(chatId, isGroupChat);
                }, 5000);
    
                // Handle Mute Status
                if (isGroupChatFlag) {
                    if (response.is_muted) {
                        $('#message-input')
                            .prop('disabled', true)
                            .attr('placeholder', '群组已禁言');
    
                        $('#send-button').prop('disabled', true);
                        $('.additional-icons button').prop('disabled', true);
                    } else {
                        $('#message-input')
                            .prop('disabled', false)
                            .attr('placeholder', '输入文字');
    
                        $('#send-button').prop('disabled', false);
                        $('.additional-icons button').prop('disabled', false);
                    }
                } else {
                    $('#message-input')
                        .prop('disabled', false)
                        .attr('placeholder', '输入文字');
    
                    $('#send-button').prop('disabled', false);
                    $('.additional-icons button').prop('disabled', false);
                }
    
                isLoadingMessages = false;
            },
    
            error: function (xhr, status, error) {
                console.error('Failed to fetch messages:', error);
                isLoadingMessages = false;
            }
        });
    }
    
    // Scroll event for lazy loading older messages
    $('.chat-conversation-box-scroll').on('scroll', debounce(function () {
        const container = $(this);
        if (container.scrollTop() === 0 && !isLoadingMessages && !allMessagesLoaded) {
            fetchMessages(currentConversationId, isGroupChat, false);
        }
    }, 200));
    
    // Click event to load messages for a selected chat
    $('.user-list-box').on('click', '.person', function () {
        const chatType = $(this).data('chat-type');
        const chatId = $(this).data('chat-id');
        isGroupChat = chatType === 'grpchat';
        currentConversationId = chatId;
        messageOffset = 0;
        allMessagesLoaded = false;
    
        $('.user-list-box .person').removeClass('active');
        $(this).addClass('active');
    
        let data = {
            impersonation_token: window.chatConfig.impersonationToken,
            limit: messageLimit,
            offset: messageOffset,
        };
    
        if (isGroupChat) {
            data.is_group_chat = 1;
        }
    
        toggleTagging(isGroupChat);
    
        // Fetch initial batch of messages
        console.log('Calling fetchMessages with:', {
            chatId,
            isGroupChat,
            initialLoad: true
        });
        
        fetchMessages(chatId, isGroupChat, true);
    
        const chatName = $(this).find('.user-name').text();
        $('.current-chat-user-name .name').text(chatName);
    
        $('.chat-not-selected').hide();
        $('.chat-box-inner').show();
        $('.chat-footer').show();
    
        if (isGroupChat) {
            $.ajax({
                url: `/grpchat/${chatId}/announcement`,
                method: 'GET',
                data: { impersonation_token: window.chatConfig.impersonationToken },
                success: function (response) {
                    updateMarquee(response.announcement);
                },
                error: function () {
                    updateMarquee('暂无公告');
                }
            });
            // Update all elements inside the overlay that use the group chat id.
            $('#grpchat-settings-overlay')
                .find('.open-settings, .open-mute-settings, .open-member-list, .invite-members, .quit-group')
                .data('grpchat-id', chatId);
    
            // Optionally log the updated attribute to verify
            console.log("Updated overlay's open-settings button data-grpchat-id:", 
                $('#grpchat-settings-overlay').find('.open-settings').attr('data-grpchat-id'));
        } else {
            updateMarquee('');
        }
    });
    
    document.getElementById('popout-send-msg').addEventListener('click', function () {
    const chatId = document.getElementById('popout-name').getAttribute('data-chat-id');

    if (!chatId) {
        alert('未找到聊天目标。');
        return;
    }

    // Close the popout
    document.getElementById('miniPopout').classList.add('d-none');

    const chatType = 'conversation';

    // Reset conversation state
    isGroupChat = false;
    currentConversationId = chatId;
    messageOffset = 0;
    allMessagesLoaded = false;

    // Remove active state from other chats
    $('.user-list-box .person').removeClass('active');

    // If the corresponding chat item exists, add active state
    const chatElement = $(`.person[data-chat-id="${chatId}"][data-chat-type="${chatType}"]`);
    if (chatElement.length > 0) {
        chatElement.addClass('active');
    }

    // Fetch initial batch of messages
    console.log('Calling fetchMessages with:', {
        chatId,
        isGroupChat,
        initialLoad: true
    });
    fetchMessages(chatId, false, true);

    // Update UI with chat name
    const chatName = chatElement.length > 0
        ? chatElement.find('.user-name').text()
        : document.getElementById('popout-name').textContent;
    $('.current-chat-user-name .name').text(chatName);

    // Show chat UI
    $('.chat-not-selected').hide();
    $('.chat-box-inner').show();
    $('.chat-footer').show();

    // Clear announcements since it's not a group chat
    updateMarquee('');
});

    // Function to scroll to bottom
    function scrollToBottom() {
        const messagesContainer = $('.chat-conversation-box-scroll');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    function formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    $('.hamburger, .chat-system .chat-box .chat-not-selected p').on('click', function() {
        $(this).closest('.chat-system').find('.user-list-box').toggleClass('user-list-box-show');
    });

    scrollToBottom();
    $('.chat-footer').hide();

    $('.search > input').on('keyup', function () {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            const searchText = $(this).val().toLowerCase();
            $('.people .person').each(function () {
                const name = $(this).find('.user-name').text().toLowerCase();
                const isMatch = name.includes(searchText);
                $(this).toggle(isMatch);
            });
        }, 300);
    });
    
    $(document).on('click', '.remove-friend-button', function () {
        const friendshipId = $(this).data('friendship-id');
    
        if (!confirm('确定要移除此好友吗？')) {
            return;
        }
    
        $.ajax({
            url: `/robot/friendlist/${friendshipId}/update-status`,
            type: 'POST',
            data: {
                status: 3,
                _token: window.chatConfig.csrfToken,
                impersonation_token: window.chatConfig.impersonationToken
            },
            success: function (response) {
                alert(response.message || '好友已移除。');
                $(`button[data-friendship-id="${friendshipId}"]`).closest('.friend-item').remove();
            },
            error: function (xhr, status, error) {
                alert(xhr.responseJSON?.message || '移除好友失败，请稍后再试。');
            }
        });
    });

    $('#audio-button').on('click', function () {
        $('#audioRecordingModal').modal('show');
    });

    $('#start-record-btn').on('click', async function () {
        // Check for microphone permissions
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('您的浏览器不支持录音功能。');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.start();

            $('#recording-indicator').show();
            $('#start-record-btn').attr('disabled', true);
            $('#stop-record-btn').attr('disabled', false);
            $('#cancel-record-btn').attr('disabled', false);

            mediaRecorder.ondataavailable = event => {
                audioChunks.push(event.data);
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                audioChunks = [];
                sendAudioMessage(audioBlob);
            };
        } catch (err) {
            console.error('录音错误:', err);
            alert('无法访问麦克风。请检查您的设备设置。');
        }
    });

    $('#stop-record-btn').on('click', function () {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
            $('#recording-indicator').hide();
            $('#start-record-btn').attr('disabled', false);
            $('#stop-record-btn').attr('disabled', true);
            $('#cancel-record-btn').attr('disabled', true);
            $('#audioRecordingModal').modal('hide');
        }
    });

    $('#cancel-record-btn').on('click', function () {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
            audioChunks = [];
            $('#recording-indicator').hide();
            $('#start-record-btn').attr('disabled', false);
            $('#stop-record-btn').attr('disabled', true);
            $('#cancel-record-btn').attr('disabled', true);
            $('#audioRecordingModal').modal('hide');
            alert('录音已取消。');
        }
    });

    function sendAudioMessage(audioBlob) {
        const activeChat = currentConversationId;
        const isGroupChat = $('.person.active').data('chat-type') === 'grpchat';
    
        if (!activeChat) {
            alert('未选择聊天。');
            return;
        }
    
        const formData = new FormData();
        formData.append('audio', audioBlob, 'audio.webm');
        formData.append('chat_id', activeChat);
        formData.append('is_group_chat', isGroupChat ? 1 : 0);
        formData.append('impersonation_token', window.chatConfig.impersonationToken);
        formData.append('_token', window.chatConfig.csrfToken);
    
        const uploadUrl = isGroupChat ? '/robot/grpchats/send-audio' : '/robot/send-audio';
    
        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status === 'success') {
                    const messagesContainer = $('.chat-conversation-box-scroll');
                    const audioPath = response.audio_url;
                    const timestamp = new Date(response.created_at).toLocaleString();

                    const audioUrl = `${window.location.origin}/storage/${audioPath}`;

                    const messageHtml = `
                        <div class="bubble me">
                            <audio controls src="${audioUrl}"></audio>
                            <span class="timestamp">${timestamp}</span>
                        </div>`;
                    messagesContainer.append(messageHtml);
                    scrollToBottom();
                } else {
                    alert(response.message || '发送音频失败。');
                }
            },
            error: function (xhr, status, error) {
                let errorMessage = '发送音频失败。';
    
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    errorMessage = '验证错误:\n';
                    for (const field in errors) {
                        if (errors.hasOwnProperty(field)) {
                            errorMessage += `${errors[field].join(', ')}\n`;
                        }
                    }
                } else if (xhr.status === 403) {
                    errorMessage = xhr.responseJSON.error || '您没有权限发送音频消息。';
                } else if (xhr.status === 500) {
                    errorMessage = '服务器内部错误，请稍后重试。';
                } else if (xhr.status >= 400 && xhr.status < 500) {
                    errorMessage = '请求错误，请检查输入或稍后重试。';
                } else if (xhr.status >= 500) {
                    errorMessage = '服务器错误，请稍后重试。';
                }
    
                alert(errorMessage);
            }
        });
    }

    $('#image-button').on('click', function () {
        console.log('Image button clicked'); // Log when the button is clicked
        $('<input type="file" accept="image/*">')
            .on('change', function (e) {
                console.log('File input change event triggered'); // Log when the file input changes
                const file = e.target.files[0];
                console.log('Selected file:', file); // Log the selected file
                if (file) {
                    console.log('Uploading file...');
                    uploadFile(file, 'image');
                } else {
                    console.log('No file selected');
                }
            })
            .click();
    });
    
    $('#document-button').on('click', function () {
        $('<input type="file" accept=".pdf,.doc,.docx,.txt">').on('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                uploadFile(file, 'document');
            }
        }).click();
    });
    
    $('#video-button').on('click', function () {
        $('<input type="file" accept="video/mp4">').on('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                uploadFile(file, 'video');
            }
        }).click();
    });
    
    function uploadFile(file, type) {
        const activeChat = currentConversationId;
        const isGroupChat = $('.person.active').data('chat-type') === 'grpchat';
    
        if (!activeChat) {
            alert('未选择聊天。');
            return;
        }
    
        const formData = new FormData();
        formData.append('file', file);
        formData.append('chat_id', activeChat);
        formData.append('is_group_chat', isGroupChat ? 1 : 0);
        formData.append('impersonation_token', window.chatConfig.impersonationToken);
        formData.append('_token', window.chatConfig.csrfToken);
    
        const uploadUrl = isGroupChat ? '/robot/grpchats/send-file' : '/robot/send-file';
    
        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                console.log('Upload File Response:', response);
                debouncedRefreshChatList();
            
                if (response.status === 'success') {
                    const messagesContainer = $('.chat-conversation-box-scroll');
                    let messageHtml = '';
                    const avatarUrl = response.sender_avatar
                        ? `${window.location.origin}/${response.sender_avatar}`
                        : 'https://qmxk.cloud/default-avatar.png'; // Default avatar if not found
            
                    if (type === 'image') {
                        const imageUrl = `${window.location.origin}/storage/${response.image_url}`;
                        const isGroupChat = response.isgroupchat ? 'true' : 'false';
                        messageHtml = `
                            <div class="message-container me">
                                <span class="timestamp">${new Date(response.created_at).toLocaleString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }).replace(',', '')}</span>
                                <div class="message-row">
                                    <div class="bubble" data-message-id="${response.id}" data-is-group-chat="${isGroupChat}" data-sender-name="${response.sender_name}">
                                        <img src="${imageUrl}" alt="Image" class="chat-image">
                                    </div>
                                    <img class="avatar me" src="${avatarUrl}" alt="${response.sender_name}'s avatar" />
                                </div>
                            </div>
                        `;
                    } else if (type === 'video') {
                        const videoUrl = `${window.location.origin}/storage/${response.video_url}`;
                        const isGroupChat = response.isgroupchat ? 'true' : 'false';
                        messageHtml = `
                            <div class="message-container me">
                                <span class="timestamp">${new Date(response.created_at).toLocaleString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }).replace(',', '')}</span>
                                <div class="message-row">
                                    <div class="bubble" data-message-id="${response.id}" data-is-group-chat="${isGroupChat}" data-sender-name="${response.sender_name}">
                                        <video controls class="chat-video">
                                            <source src="${videoUrl}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                    <img class="avatar me" src="${avatarUrl}" alt="${response.sender_name}'s avatar" />
                                </div>
                            </div>
                        `;
                    } else if (type === 'document') {
                        const docUrl = `${window.location.origin}/storage/${response.doc_url}`;
                        const isGroupChat = response.isgroupchat ? 'true' : 'false';
                        const fileName = escapeHTML(file.name);
                        messageHtml = `
                            <div class="message-container me">
                                <span class="timestamp">${new Date(response.created_at).toLocaleString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }).replace(',', '')}</span>
                                <div class="message-row">
                                    <div class="bubble" data-message-id="${response.id}" data-is-group-chat="${isGroupChat}" data-sender-name="${response.sender_name}">
                                        <a href="${docUrl}" target="_blank" class="chat-document">
                                            <i class="fas fa-file-alt"></i> ${fileName}
                                        </a>
                                    </div>
                                    <img class="avatar me" src="${avatarUrl}" alt="${response.sender_name}'s avatar" />
                                </div>
                            </div>
                        `;
                    }
            
                    // Append the message to the chat container and scroll to the bottom
                    messagesContainer.append(messageHtml);
                    scrollToBottom();
            
                    // Update lastMessageId
                    if (response.id > lastMessageId) {
                        lastMessageId = response.id;
                    }
            
                } else {
                    alert(response.message || '上传文件失败。');
                }
            },

            error: function (xhr, status, error) {
                let errorMessage = '上传文件失败。';
    
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    errorMessage = '验证错误:\n';
                    for (const field in errors) {
                        if (errors.hasOwnProperty(field)) {
                            errorMessage += `${errors[field].join(', ')}\n`;
                        }
                    }
                } else if (xhr.status === 403) {
                    errorMessage = xhr.responseJSON.error || '您没有权限上传文件。';
                } else if (xhr.status === 500) {
                    errorMessage = '服务器内部错误，请稍后重试。';
                } else if (xhr.status >= 400 && xhr.status < 500) {
                    errorMessage = '请求错误，请检查输入或稍后重试。';
                } else if (xhr.status >= 500) {
                    errorMessage = '服务器错误，请稍后重试。';
                }
    
                alert(errorMessage);
            }
        });
    }

    function extractFileName(url) {
        return url.substring(url.lastIndexOf('/') + 1);
    }
    
    function renderMessage(message, bubbleClass) {
        let content = '';

        if (message.image_url) {
            const imageUrl = `${window.location.origin}/storage/${message.image_url}`;
            content += `<img src="${imageUrl}" alt="Image" class="chat-image">`;
        }

        if (message.doc_url) {
            const docUrl = `${window.location.origin}/storage/${message.doc_url}`;
            const fileName = extractFileName(message.doc_url);
            content += `<a href="${docUrl}" target="_blank" class="chat-document">
                            <i class="fas fa-file-alt"></i> ${fileName}
                        </a>`;
        }

        if (message.audio_url) {
            const audioUrl = `${window.location.origin}/storage/${message.audio_url}`;
            content += `<audio controls src="${audioUrl}"></audio>`;
        }

        if (message.message) {
            content += `<p>${message.message}</p>`;
        }

        return `<div class="bubble ${bubbleClass}">
                    <p style="font-size: x-small; color: grey;">${message.user.name}</p>
                    ${content}
                    <span class="timestamp">${new Date(message.created_at).toLocaleString()}</span>
                </div>`;
    }
    
    let step = 1;
    const maxSteps = 3;

    $('#group-chat-button').on('click', function () {
        $('#groupChatModal').modal('show');
        step = 1;
        updateSteps();
    });

    function loadFriendList() {
        const impersonationToken = window.chatConfig.impersonationToken;
        const getFriendListUrl = "/robot/friendlist"; // Ensure this points to the correct route
    
        $.ajax({
            url: getFriendListUrl,
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            dataType: 'json', // Expect JSON response
            success: function (response) {
                console.log('Friend list response:', response);
                const friendListContainer = $('#friend-selection');
                friendListContainer.empty();
    
                if (response && Array.isArray(response.friends)) {
                    if (response.friends.length > 0) {
                        response.friends.forEach((friend) => {
                            const friendHtml = `
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="${friend.id}" id="friend-${friend.id}">
                                    <label class="form-check-label" for="friend-${friend.id}">
                                        ${friend.realname}
                                    </label>
                                </div>
                            `;
                            friendListContainer.append(friendHtml);
                        });
                    } else {
                        friendListContainer.html('<p class="text-center">没有好友可用。</p>');
                    }
                } else {
                    friendListContainer.html('<p class="text-center text-danger">数据格式错误，请联系管理员。</p>');
                }
            },
            error: function (xhr) {
                console.error('Error fetching friend list:', xhr.responseText);
                $('#friend-selection').html('<p class="text-center text-danger">无法加载好友列表，请稍后再试。</p>');
            }
        });
    }

    function updateSteps() {
        for (let i = 1; i <= maxSteps; i++) {
            $(`#group-chat-step-${i}`).hide();
        }
        $(`#group-chat-step-${step}`).show();

        $('#prev-step').toggle(step > 1);
        $('#next-step').toggle(step < maxSteps);
        $('#create-group-chat').toggle(step === maxSteps);

        if (step === 2) {
            loadFriendList();
        } else if (step === 3) {
            const groupName = $('#group-chat-name').val();
            $('#confirm-group-name').text(groupName);

            const memberList = $('#friend-selection input:checked')
                .map(function () {
                    return `<li>${$(this).next('label').text()}</li>`;
                })
                .get()
                .join('');
            $('#confirm-group-members').html(memberList);
        }
    }

    $('#next-step').on('click', function () {
        if (step === 1 && $('#group-chat-name').val().trim() === '') {
            alert('群组名称不能为空！');
            return;
        }
        step++;
        updateSteps();
    });

    $('#prev-step').on('click', function () {
        step--;
        updateSteps();
    });

    $('#create-group-chat').on('click', function () {
        const impersonationToken = window.chatConfig.impersonationToken;
        const groupName = $('#group-chat-name').val();
        const members = $('#friend-selection input:checked').map(function () {
            return $(this).val();
        }).get();
    
        if (members.length === 0) {
            alert('请选择至少一个好友加入群组！');
            return;
        }
    
        const createGrp = "/robot/grpchats/create";
    
        $.ajax({
            url: createGrp,
            type: 'POST',
            data: {
                chatname: groupName,
                members: members,
                impersonation_token: impersonationToken,
                _token: window.chatConfig.csrfToken,
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert('群组创建成功！');
                    $('#groupChatModal').modal('hide');

                    const newGrpchat = response.grpchat;
                    const latestMessage = response.latest_message;

                    const chatHtml = `
                        <div class="person"
                             data-chat-type="grpchat"
                             data-chat-id="${newGrpchat.id}">
                            <div class="user-info">
                                <div class="f-head">
                                    ${newGrpchat.avatar ? `<img src="${window.location.origin}/storage/${newGrpchat.avatar}" alt="Group Avatar" class="avatar">` : `<div class="avatar-default"></div>`}
                                </div>
                                <div class="f-body" style="margin-left:5px">
                                    <div class="meta-info">
                                        <span class="user-name">${newGrpchat.chatname}</span>
                                        <span class="user-meta-time">
                                            ${latestMessage.created_at ? new Date(latestMessage.created_at).toLocaleString() : '有新消息'}
                                        </span>
                                        <span class="unread-count badge bg-danger" style="display:none;"></span>
                                    </div>
                                    <span class="preview">
                                        ${latestMessage.message || '有新消息'}
                                    </span>

                                    <div class="settings-icon" style="margin-top: 5px;">
                                        <a href="javascript:void(0)" class="open-settings" data-grpchat-id="${newGrpchat.id}" title="Group Settings">
                                            <i class="fas fa-cogs"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="open-mute-settings" data-grpchat-id="${newGrpchat.id}" title="Mute Members">
                                            <i class="fas fa-volume-mute text-danger"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    $('.people').prepend(chatHtml);
                } else {
                    alert(response.message || '群组创建失败，请重试。');
                }
            },
            error: function (xhr, status, error) {
                alert('群组创建失败，请重试。');
            },
        });
    });

    $(document).on('click', '.open-settings', function (e) {
        e.preventDefault();
        const grpchatId = $(this).data('grpchat-id');
        console.log('Is Grp:', grpchatId);
        
        fetchAvailableMembers(grpchatId);
    
        // Set the value on the correct element
        $('#group-settings-grpchat-id').val(grpchatId);
        
        // Log the value from the same element
        console.log('Set grpchat_id in form:', $('#group-settings-grpchat-id').val());
        
        $.ajax({
            url: `/grpchat/${grpchatId}/settings`,
            method: 'GET',
            data: { impersonation_token: window.chatConfig.impersonationToken },
            success: function (response) {
                // Now allow if the user is either the owner or an admin
                if (!response.is_owner && !response.is_admin) {
                    alert('您无法打开此设置，因为您不是管理员或群主。');
                    return;
                }
        
                // Populate the common settings for both owners and admins
                $('#group-name').val(response.chatname);
                $('#group-announcement').val(response.announcement || '');
        
                // Populate settings toggles
                $('#add-friend-toggle').prop('checked', response.settings.add_friend);
                $('#hide-members-toggle').prop('checked', response.settings.hide_members);
                $('#allow-invite-toggle').prop('checked', response.settings.allow_invite);
                $('#block-quit-toggle').prop('checked', response.settings.block_quit);
                $('#mute-chat-toggle').prop('checked', response.settings.mute_chat);
        
                // Show admin management only for owners (if that’s your intended logic)
                if (response.is_owner) {
                    $('#admin-management').show();
        
                    // Populate current admins
                    const currentAdmins = response.admins;
                    const adminsList = $('#current-admins');
                    adminsList.empty();
                    currentAdmins.forEach(admin => {
                        adminsList.append(`
                            <li class="list-group-item d-flex justify-content-between align-items-center admin-item">
                                ${admin.nickname} 【${admin.realname}】
                                <button class="btn btn-danger btn-sm remove-admin-btn" data-admin-id="${admin.id}">
                                    移除
                                </button>
                            </li>
                        `);
                    });
        
                    // Populate add-admin select with non-admin members
                    const nonAdminMembers = response.members.filter(member => !currentAdmins.some(admin => admin.id === member.id));
                    const addAdminSelect = $('#add-admin-select');
                    addAdminSelect.empty().append('<option value="">选择用户</option>');
                    nonAdminMembers.forEach(member => {
                        addAdminSelect.append(`<option value="${member.id}">${member.nickname} 【${member.realname}】</option>`);
                    });
                } else {
                    $('#admin-management').hide();
                }
        
                // Populate member list for removal
                $('#member-list').empty();
                response.members.forEach(member => {
                    const listItem = `
                        <li class="list-group-item d-flex justify-content-between align-items-center member-item">
                            ${member.nickname} 【${member.realname}】
                            <button type="button" class="btn btn-danger btn-sm remove-member" data-id="${member.id}" data-grpchat-id="${grpchatId}">移除</button>
                        </li>
                    `;
                    $('#member-list').append(listItem);
                });
                $('#groupSettingsModal').modal('show');
            },
            error: function (error) {
                alert('无法加载群组设置');
                console.error('Error loading group settings:', error);
            }
        });
    });
    
    function fetchAvailableMembers(grpchatId) {
        // Your existing logic to fetch and populate #available-members
    }
    
    $('#remove-member-search').on('input', function () {
        const query = $(this).val().toLowerCase().trim();
    
        $('#member-list .member-item').each(function () {
            const memberInfo = $(this).text().toLowerCase().trim();
            const isMatch = memberInfo.includes(query);
    
            if (isMatch) {
                $(this).removeClass('d-none').addClass('d-flex');
            } else {
                $(this).removeClass('d-flex').addClass('d-none');
            }
        });
    });
    
    $('#admin-search').on('input', function () {
        const query = $(this).val().toLowerCase().trim();
    
        $('#current-admins .admin-item').each(function () {
            const adminInfo = $(this).find('span').text().toLowerCase().trim();
            const isMatch = adminInfo.includes(query);
    
            if (isMatch) {
                $(this).removeClass('d-none').addClass('d-flex');
            } else {
                $(this).removeClass('d-flex').addClass('d-none');
            }
        });
    });

    $(document).on('click', '.open-mute-settings', function (e) {
        e.preventDefault();
        const grpchatId = $(this).data('grpchat-id');
        $('#grpchat-id-mute').val(grpchatId);
    
        // Fetch the current members and muted members
        $.ajax({
            url: `/grpchat/${grpchatId}/settings`,
            method: 'GET',
            data: { impersonation_token: window.chatConfig.impersonationToken },
            success: function (response) {
                console.log('Is Owner:', response.is_owner);
    
                // Allow access if the user is either the owner or an admin
                if (!response.is_owner && !response.is_admin) {
                    alert('您无法打开此设置，因为您不是管理员或群主。');
                    return; // Stop execution to prevent opening the modal
                }
                
                $('#mute-member-list').empty();
                const mutedMembers = response.settings.mute_members || [];
    
                response.members.forEach(member => {
                    // Ensure IDs are compared as strings
                    const isChecked = mutedMembers.map(String).includes(String(member.id));
                    const listItem = `
                        <li class="list-group-item d-flex justify-content-between align-items-center member-item">
                            <span class="member-info">
                                ${member.nickname} 【${member.realname}】
                            </span>
                            <input type="checkbox" class="form-check-input mute-checkbox" data-member-id="${member.id}" ${isChecked ? 'checked' : ''}>
                        </li>
                    `;
                    $('#mute-member-list').append(listItem);
                });
    
                $('#muteSettingsModal').modal('show');
                // Reset the search input when modal opens
                $('#member-search').val('');
            },
            error: function (error) {
                alert('无法加载禁言设置');
                console.error('Error loading mute settings:', error);
            }
        });
    });

    $('#member-search').on('input', function () {
        const query = $(this).val().toLowerCase().trim();

        $('#mute-member-list .member-item').each(function () {
            const memberInfo = $(this).find('.member-info').text().toLowerCase().trim();
            const isMatch = memberInfo.includes(query);

            if (isMatch) {
                $(this).removeClass('d-none').addClass('d-flex');
            } else {
                $(this).removeClass('d-flex').addClass('d-none');
            }
        });
    });

    $('#save-mute-settings').on('click', function () {
        const grpchatId = $('#grpchat-id-mute').val();
        const mutedMembers = [];

        // Collect all checked members
        $('#mute-member-list .mute-checkbox:checked').each(function () {
            mutedMembers.push($(this).data('member-id'));
        });

        $.ajax({
            url: `/grpchat/settings/mute-members`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken,
            },
            data: {
                grpchat_id: grpchatId,
                mute_members: mutedMembers,
                impersonation_token: window.chatConfig.impersonationToken,
            },
            success: function () {
                alert('禁言设置已保存');
                $('#muteSettingsModal').modal('hide');
            },
            error: function (xhr, status, error) {
                alert('保存失败，请重试');
            },
        });
    });

    $('#save-group-settings').on('click', function () {
        console.log('Saving group settings. grpchat_id:', $('#grpchat-id').val());
        
        const formData = new FormData($('#group-settings-form')[0]);
        formData.append('impersonation_token', window.chatConfig.impersonationToken);
    
        // Append toggle values
        formData.append('add_friend', $('#add-friend-toggle').is(':checked') ? 1 : 0);
        formData.append('hide_members', $('#hide-members-toggle').is(':checked') ? 1 : 0);
        formData.append('allow_invite', $('#allow-invite-toggle').is(':checked') ? 1 : 0);
        formData.append('block_quit', $('#block-quit-toggle').is(':checked') ? 1 : 0);
        formData.append('mute_chat', $('#mute-chat-toggle').is(':checked') ? 1 : 0);
        
        for (let pair of formData.entries()) {
            console.log(pair[0]+ ': ' + pair[1]);
        }
    
        $.ajax({
            url: '/grpchat/settings',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function () {
                alert('群组设置已保存');
                $('#groupSettingsModal').modal('hide');
                location.reload();
            },
            error: function (xhr, status, error) {
                alert('保存失败，请重试');
            }
        });
    });

    // 添加管理员按钮点击事件
    $('#add-admin-btn').on('click', function () {
        const selectedUserId = $('#add-admin-select').val();
        const grpchatId = $('#group-settings-grpchat-id').val();
    
        if (!selectedUserId) {
            alert('请先选择一个用户作为管理员。');
            return;
        }
    
        if (!grpchatId) {
            alert('无法获取群组ID，请刷新页面或重试。');
            return;
        }
    
        $.ajax({
            url: `/grpchat/${grpchatId}/add-admin`,
            type: 'POST',
            data: {
                admin_id: selectedUserId,
                grpchat_id: grpchatId,
                impersonation_token: window.chatConfig.impersonationToken
            },
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function (response) {
                alert('管理员已成功添加。');
                console.log('Add Admin Response:', response);
                refreshAdminsList(grpchatId);
            },
            error: function (xhr) {
                alert(`错误: ${xhr.responseJSON.message}`);
                console.error('Add Admin Error:', xhr);
            }
        });
    });
    
    // 移除管理员按钮点击事件
    $(document).on('click', '.remove-admin-btn', function () {
        const adminId = $(this).data('admin-id');
        const grpchatId = $('#group-settings-grpchat-id').val();
    
        console.log('Admin ID:', adminId);
        console.log('Group Chat ID:', grpchatId);
    
        if (!adminId) {
            alert('无法获取管理员ID。');
            return;
        }
    
        if (!grpchatId) {
            alert('无法获取群组ID，请刷新页面或重试。');
            return;
        }
    
        if (!confirm('确定要移除此管理员吗？')) {
            return;
        }
    
        $.ajax({
            url: `/grpchat/${grpchatId}/remove-admin`,
            method: 'POST',
            data: {
                admin_id: adminId,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken
            },
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function (response) {
                alert(response.message || '管理员已移除');
                console.log('Remove Admin Response:', response);
                $(`button.remove-admin-btn[data-admin-id="${adminId}"]`).closest('li').remove();
            },
            error: function (xhr, status, error) {
                alert(xhr.responseJSON.message || '移除管理员失败，请重试。');
                console.error('Remove Admin Error:', xhr);
            }
        });
    });
    
    // 添加成员按钮点击事件
    $('#add-member-btn').on('click', function () {
        const memberId = $('#available-members').val();
        const grpchatId = $('#group-settings-grpchat-id').val();
    
        if (!memberId) {
            alert('请选择要添加的成员');
            return;
        }
    
        if (!confirm('确定要添加此成员吗？')) {
            return;
        }
    
        $('#add-member-btn').attr('disabled', true).text('添加中...');
    
        $.ajax({
            url: `/grpchat/${grpchatId}/add-member`,
            method: 'POST',
            data: {
                member_id: memberId,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert('成员已成功添加。');
                    console.log('Add Member Response:', response);
    
                    // Add new member to the members list
                    const newMember = response.new_member;
                    $('#member-list').append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            ${newMember.name} 【${newMember.realname}】
                            <button type="button" class="btn btn-danger btn-sm remove-member" data-id="${newMember.id}" data-grpchat-id="${grpchatId}">移除</button>
                        </li>
                    `);
    
                    // Remove the added member from the dropdown
                    $(`#available-members option[value="${newMember.id}"]`).remove();
    
                    // Re-enable the button
                    $('#add-member-btn').attr('disabled', false).text('添加');
                } else {
                    alert(response.message || '添加成员失败。');
                    console.error('Add Member Failed:', response);
                }
            },
            error: function (xhr, status, error) {
                alert(xhr.responseJSON?.message || '添加成员失败，请重试。');
                console.error('Add Member Error:', xhr);
            },
            complete: function () {
                $('#add-member-btn').attr('disabled', false).text('添加');
            }
        });
    });
    
    // 移除成员按钮点击事件
    $(document).on('click', '.remove-member', function () {
        const memberId = $(this).data('id');
        const grpchatId = $('#group-settings-grpchat-id').val();
    
        if (!confirm('确定要移除此成员吗？')) {
            return;
        }
    
        $.ajax({
            url: `/grpchat/${grpchatId}/remove-member`,
            method: 'POST',
            data: {
                member_id: memberId,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken
            },
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function (response) {
                alert(response.message || '成员已移除');
                console.log('Remove Member Response:', response);
                $(`button.remove-member[data-id="${memberId}"]`).closest('li').remove();
            },
            error: function (xhr, status, error) {
                alert(xhr.responseJSON.error || '移除成员失败，请重试。');
                console.error('Remove Member Error:', xhr);
            }
        });
    });
    
    function refreshAdminsList(grpchatId) {
        $.ajax({
            url: `/grpchat/${grpchatId}/admins`, // Endpoint to fetch current admins
            type: 'GET',
            success: function (response) {
                const adminsList = $('#current-admins');
                const addAdminSelect = $('#add-admin-select');
    
                // Clear existing lists
                adminsList.empty();
                addAdminSelect.empty().append('<option value="">选择用户</option>');
    
                // Populate admins list
                response.admins.forEach(admin => {
                    adminsList.append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            ${admin.name} 【${admin.realname}】
                            <button class="btn btn-danger btn-sm remove-admin-btn" data-admin-id="${admin.id}">移除</button>
                        </li>
                    `);
                });
    
                // Populate add-admin dropdown
                response.non_admin_users.forEach(user => {
                    addAdminSelect.append(`<option value="${user.id}">${user.name} 【${user.realname}】</option>`);
                });
            },
            error: function (xhr) {
                alert('无法刷新管理员列表，请重试。');
            }
        });
    }
    
    function refreshMembers(grpchatId) {
        $.ajax({
            url: `/grpchat/${grpchatId}/members`,
            method: 'GET',
            success: function (response) {
                if (response.status === 'success') {
                    alert(response.message || '成员已成功添加。');
            
                    // Add new member to the members list
                    const newMember = response.new_member;
                    $('#member-list').append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            ${newMember.nickname} 【${newMember.realname}】
                            <button type="button" class="btn btn-danger btn-sm remove-member" data-id="${newMember.id}" data-grpchat-id="${grpchatId}">移除</button>
                        </li>
                    `);
            
                    // Remove the added member from the dropdown
                    $(`#available-members option[value="${newMember.id}"]`).remove();
                } else {
                    alert(response.message || '添加成员失败。');
                    console.error('Add Member Failed:', response);
                }
            }
        });
    }

    // Function to fetch available members for adding to group
    function fetchAvailableMembers(grpchatId) {
        $.ajax({
            url: `/grpchat/${grpchatId}/available-members`,
            method: 'GET',
            data: { impersonation_token: window.chatConfig.impersonationToken },
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function (response) {
                const membersDropdown = $('#available-members');
                membersDropdown.empty();

                response.available_members.forEach(member => {
                    membersDropdown.append(`<option value="${member.id}">${member.nickname}【${member.realname}】</option>`);
                });
            },
            error: function (error) {
                alert('无法加载可用的成员列表');
            }
        });
    }

    // Disable tagging in personal chats
    function toggleTagging(isGroupChat) {
        if (isGroupChat) {
            $('#message-input').attr('data-tagging-enabled', 'true');
        } else {
            $('#message-input').attr('data-tagging-enabled', 'false');
            hideSuggestions();
        }
    }

    $('#message-input').on('keyup', function (e) {
        const isTaggingEnabled = $(this).attr('data-tagging-enabled') === 'true';
        if (!isTaggingEnabled) return;
    
        const input = $(this);
        const cursorPosition = input.prop("selectionStart");
        const text = input.val().substring(0, cursorPosition);
        const atIndex = text.lastIndexOf('@');
    
        // Check if "@" is typed and it is at the start or preceded by a space
        if (atIndex >= 0 && (atIndex === 0 || /\s/.test(text.charAt(atIndex - 1)))) {
            const query = text.substring(atIndex + 1); // Get the query after "@"
            const grpchatId = currentConversationId;
    
            // Fetch users even if query is empty
            if (query.length >= 0) {
                $.ajax({
                    url: '/robot/search-users',
                    type: 'GET',
                    data: {
                        impersonation_token: window.chatConfig.impersonationToken,
                        query: query, // This can be empty to fetch all users
                        grpchat_id: grpchatId
                    },
                    success: function (response) {
                        if (response.users && response.users.length > 0) {
                            showSuggestions(response.users, atIndex, query);
                        } else {
                            hideSuggestions();
                        }
                    },
                    error: function () {
                        hideSuggestions();
                    }
                });
            } else {
                hideSuggestions();
            }
        } else {
            hideSuggestions();
        }
    });

    // Function to show autocomplete suggestions
    function showSuggestions(users, atIndex, query) {
        const suggestionsContainer = $('#user-suggestions');
    
        if (suggestionsContainer.length === 0) {
            $('body').append('<div id="user-suggestions" class="suggestions-dropdown"></div>');
        }
    
        suggestionsContainer.empty();
    
        // Add the "@全部人" option at the top
        const tagAllItem = `<div class="suggestion-item" data-user-id="all" data-user-name="全部人">
                                <span class="suggestion-name">@全部人</span>
                            </div>`;
        suggestionsContainer.append(tagAllItem);
    
        // Add regular user suggestions
        users.forEach(user => {
            const userItem = `<div class="suggestion-item" data-user-id="${user.id}" data-user-name="${user.realname}">
                                <img src="${user.avatar || 'https://via.placeholder.com/30'}" alt="${user.realname}" class="suggestion-avatar">
                                <span class="suggestion-name">@${user.realname}</span>
                            </div>`;
            suggestionsContainer.append(userItem);
        });
    
        // Position the suggestions container near the cursor
        const inputOffset = $('#message-input').offset();
        const inputHeight = $('#message-input').outerHeight();
    
        suggestionsContainer.css({
            position: 'absolute',
            top: inputOffset.top + inputHeight,
            left: inputOffset.left,
            border: '1px solid #ccc',
            background: '#fff',
            'z-index': 1000,
            width: $('#message-input').outerWidth(),
            'max-height': '200px',
            overflow: 'auto',
        }).show();
    }

    // Function to hide autocomplete suggestions
    function hideSuggestions() {
        $('#user-suggestions').hide().empty();
    }

    // Handle clicking on a suggestion to insert the tagged username
    $(document).on('click', '.suggestion-item', function () {
        const userName = $(this).data('user-name');
        const userId = $(this).data('user-id');
    
        const input = $('#message-input');
        const cursorPosition = input.prop("selectionStart");
        const text = input.val();
        const atIndex = text.lastIndexOf('@', cursorPosition - 1);
    
        if (atIndex >= 0) {
            const beforeAt = text.substring(0, atIndex);
            const afterAt = text.substring(cursorPosition);
    
            // Add special handling for "@全部人"
            const newText = beforeAt + '@' + userName + ' ' + afterAt;
            input.val(newText);
            input.focus();
    
            hideSuggestions();
        }
    
        // Handle backend processing if user selects "@全部人"
        if (userId === 'all') {
            // You can add additional logic here to inform the backend about the "tag all" action.
            console.log("Tagging all users");
        }
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#user-suggestions, #message-input').length) {
            hideSuggestions();
        }
    });

    // Handle clicking on a tagged user
    $(document).on('click', '.tagged-user', function () {
        const userId = $(this).data('user-id');
        window.location.href = `/user/${userId}`;
    });
    
    //Live pusher
    function handleIncomingMessage(data) {
        // Log the incoming data for debugging
        console.log("Incoming message data:", data);
    
        // Check the type of the incoming data
        switch (data.type) {
            case 'grpchat':
                console.log("Handling new group chat message.");
                handleNewMessageEvent(data);
                break;
            case 'conversation':
                console.log("Handling new personal conversation message.");
                handleNewMessageEvent(data);
                break;
            case 'grecall':
                console.log(`Handling group chat message recall. Message ID: ${data.id}`);
                handleRecallEvent(data);
                break;
            case 'crecall':
                console.log(`Handling personal conversation message recall. Message ID: ${data.id}`);
                handleRecallEvent(data);
                break;
            default:
                console.warn(`Unknown message type received: ${data.type}`);
        }
    }
    
    function handleNewMessageEvent(data) {
        const chatType = data.type;
        const chatId = chatType === 'grpchat' ? data.grpchat_id : data.conversation_id;
    
        const messagesContainer = $('.chat-conversation-box-scroll');
        const chatListContainer = $('.people');
        const activeChatId = currentConversationId; // Currently active conversation ID
    
        // If the message belongs to the current active conversation, append it
        if (chatId == activeChatId) {
            const bubbleClass = 'you'; // Incoming messages are 'you'
            const messageHtml = createMessageBubble(data, bubbleClass);
            messagesContainer.append(messageHtml);
            scrollToBottom();
        }
    
        // Update or add the chat preview in the chat list
        const chatElement = chatListContainer.find(`.person[data-chat-type="${chatType}"][data-chat-id="${chatId}"]`);
        if (chatElement.length) {
            const previewText = data.message || '有新消息';
            const timeText = new Date(data.created_at).toLocaleString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).replace(',', '');

    
            chatElement.find('.preview').text(previewText);
            chatElement.find('.user-meta-time').text(timeText);
    
            // Increment unread count if the chat is inactive
            if (chatId != activeChatId) {
                const currentUnread = parseInt(chatElement.find('.unread-count').text()) || 0;
                chatElement.find('.unread-count').show().text(currentUnread + 1);
            }
    
            // Move the chat to the top
            chatElement.prependTo(chatListContainer);
        } else {
            // Handle new chats not yet in the chat list
            const newChatHtml = `
                <div class="person" data-chat-type="${chatType}" data-chat-id="${chatId}">
                    <div class="user-info">
                        <span class="name">${data.sender_nickname || data.sender_name || '新消息'}</span>
                        <span class="preview">${data.message}</span>
                    </div>
                    <span class="user-meta-time">${new Date(data.created_at).toLocaleString()}</span>
                    <span class="unread-count">1</span>
                </div>`;
            chatListContainer.prepend(newChatHtml);
        }
    }

    function handleRecallEvent(data) {
        console.log('Handling recall event:', data);
    
        // Log all keys and values for debugging
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                console.log(`${key}: ${data[key]}`);
            }
        }
    
        const messageId = data.id;
        const recallMessage = "【信息已经撤回】";
        let chatType = null;
        let chatId = null;
    
        // Determine the chat type and ID based on the event type
        if (data.type === 'grecall') {
            chatType = 'grpchat';
            chatId = data.id;
            console.log(`Determined chat type: ${chatType}, chat ID: ${chatId}`);
        } else if (data.type === 'crecall') {
            chatType = 'conversation';
            chatId = data.conversation_id;
            console.log(`Determined chat type: ${chatType}, chat ID: ${chatId}`);
        } else {
            console.warn(`Unknown recall type: ${data.type}`);
            return;
        }
    
        if (!chatId) {
            console.warn('Chat ID is missing in the recall event data.');
            return;
        }
    
        const chatListContainer = $('.people');
        let chatElement;
        
        if (data.type === 'crecall') {
            chatElement = chatListContainer.find(`.person[data-chat-id="${chatId}"]`);
        } else if (data.type === 'grecall') {
            chatType = 'grpchat';
            chatId = data.id; // Use the message ID directly
            console.log(`Determined chat type: ${chatType}, chat ID: ${chatId}`);
        
            // Search for the message-container that contains the bubble with the given data attributes
            const messageContainer = $(`.message-container .bubble[data-is-group-chat="true"][data-message-id="${chatId}"]`).closest('.message-container');
            
            if (messageContainer.length) {
                console.log('Found message container:', messageContainer);
        
                // Hide the entire message container by setting its display to 'none'
                messageContainer.css('display', 'none');
                console.log(`Message container with message ID ${chatId} has been hidden.`);
            } else {
                console.warn(`Message container with ID ${chatId} not found.`);
            }
        }
    
        // Check if the recalled message is in the active chat
        if (chatId == currentConversationId) {
            console.log('Recall is in the active chat.');
            const messagesContainer = $('.chat-conversation-box-scroll');
            const messageBubble = messagesContainer.find(`.bubble[data-message-id="${messageId}"]`);
            console.log('Found message bubble:', messageBubble.length ? messageBubble : 'None');
        
            if (messageBubble.length) {
                // Locate the message-container that contains the bubble
                const messageContainer = messageBubble.closest('.message-container');
        
                if (messageContainer.length) {
                    // Hide the entire message container
                    messageContainer.css('display', 'none');
                    console.log(`Message container with message ID ${messageId} has been hidden.`);
                } else {
                    console.warn(`Message container for message ID ${messageId} not found.`);
                }
            } else {
                console.warn(`Message with ID ${messageId} not found in the active chat.`);
            }
        } else {
            console.log('Recall is in an inactive chat.');
        }

    
        // Update the chat preview
        if (chatElement.length) {
            const timeText = new Date(data.created_at).toLocaleString();
            const messageContainer = chatElement.closest('.message-container');
            chatElement.find('.preview').text(recallMessage);
            chatElement.find('.user-meta-time').text(timeText);
            console.log('Updated chat preview with recall message and timestamp.');
        } else {
            console.warn('Chat element not found in the chat list.');
        }
    
        // Manage unread counts if the chat is inactive
        if (chatId != currentConversationId) {
            const unreadCountElement = chatElement.find('.unread-count');
            let currentUnread = parseInt(unreadCountElement.text()) || 0;
            unreadCountElement.show().text(currentUnread + 1);
            console.log(`Incremented unread count to ${currentUnread + 1} for chat ID ${chatId}.`);
        }
    }

    function createMessageBubble(data, bubbleClass) {
        // Initialize message content
        let messageContent = '';
        const isGroupChat = data.type === 'grpchat';
    
        // Handle image attachments
        if (data.image_url) {
            const imageUrl = `${window.location.origin}/storage/${data.image_url}`;
            messageContent += `<img src="${imageUrl}" alt="Image" class="chat-image"
                                data-message-id="${data.id}" 
                                data-is-group-chat="${isGroupChat}" 
                                data-sender-name="${data.sender_nickname}">`;
        }
    
        // Handle document attachments
        if (data.doc_url) {
            const docUrl = `${window.location.origin}/storage/${data.doc_url}`;
            const fileName = extractFileName(data.doc_url);
            messageContent += `<a href="${docUrl}" target="_blank" class="chat-document" 
                                data-message-id="${data.id}" 
                                data-is-group-chat="${isGroupChat}" 
                                data-sender-name="${data.sender_nickname}">
                                    <i class="fas fa-file-alt"></i> ${fileName}
                               </a>`;
        }
    
        // Handle video attachments
        if (data.video_url) {
            const videoUrl = `${window.location.origin}/storage/${data.video_url}`;
            messageContent += `<video controls class="chat-video"
                                data-message-id="${data.id}" 
                                data-is-group-chat="${isGroupChat}" 
                                data-sender-name="${data.sender_nickname}">
                                    <source src="${videoUrl}" type="video/mp4">
                                    Your browser does not support the video tag.
                               </video>`;
        }
    
        // Handle audio attachments
        if (data.audio_url) {
            const audioUrl = `${window.location.origin}/storage/${data.audio_url}`;
            messageContent += `<audio controls src="${audioUrl}" 
                                data-message-id="${data.id}" 
                                data-is-group-chat="${isGroupChat}" 
                                data-sender-name="${data.sender_nickname}"></audio>`;
        }
    
        // Handle message content with tagged users (for group chats only)
        if (data.message) {
            if (data.type === 'grpchat' && data.tagged_users && Array.isArray(data.tagged_users)) {
                data.tagged_users.forEach(taggedUser => {
                    const tagPattern = new RegExp('@' + taggedUser.name, 'g');
                    const tagSpan = `<span class="tagged-user" data-user-id="${taggedUser.id}">@${taggedUser.name}</span>`;
                    data.message = data.message.replace(tagPattern, tagSpan);
                });
            }
    
            messageContent += `<p class="preserve-whitespace" 
                                data-message-id="${data.id}" 
                                data-is-group-chat="${isGroupChat}" 
                                data-sender-name="${data.sender_nickname}">
                                    ${data.message}
                               </p>`;
        }
    
        // Handle reply context
        let replyContext = '';
        if (data.reply_to_id) {
            replyContext = `
                <div class="reply-context">
                    <strong>${data.reply_to_user_name || 'Unknown'}:</strong> ${data.reply_to_message || ''}
                </div>
            `;
        }
    
        // Format timestamp
        const formattedTimestamp = new Date(data.created_at).toLocaleString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).replace(',', '');
    
        // Avatar and username
        const senderAvatar = data.sender_avatar
            ? `${window.location.origin}/storage/${data.sender_avatar}`
            : `${window.location.origin}/storage/default-avatar.png`;
        const senderName = data.sender_nickname || data.sender_name || 'Unknown';
    
        // Final HTML structure
        return `
            <div class="message-container ${bubbleClass}">
                <span class="timestamp">${formattedTimestamp}</span>
                <div class="message-row">
                    ${bubbleClass === 'you' ? `<img class="avatar you" src="${senderAvatar}" alt="${senderName}'s avatar" />` : ''}
                    <div class="bubble" data-message-id="${data.id}" data-is-group-chat="${isGroupChat}" data-sender-name="${data.sender_nickname}">
                    <div class="sender-name">${senderName}</div>
                        ${replyContext}
                        ${messageContent}
                    </div>
                    ${bubbleClass === 'me' ? `<img class="avatar me" src="${senderAvatar}" alt="${senderName}'s avatar" />` : ''}
                </div>
            </div>
        `;
    }

    // Open the Search and Add Friend Modal when the button is clicked
    $('#search-add-friend-button').on('click', function () {
        $('#searchAddFriendModal').modal('show');
        $('#search-query').val(''); // Clear previous input
        $('#search-results').empty(); // Clear previous results
    });
    
    // Handle Search Friend Form Submission
    $('#search-friend-form').on('submit', function (e) {
        e.preventDefault();
        const query = $('#search-query').val().trim();
    
        if (query === '') {
            alert('请输入搜索内容。');
            return;
        }
    
        $.ajax({
            url: `/robot/search-friend`, // Ensure this route points to your searchFriend controller method
            type: 'POST', // Assuming you have a POST route; adjust if it's GET
            data: {
                query: query,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken,
            },
            success: function (response) {
                if (response.status === 'success') {
                    // Construct a single friend object from the response
                    const friend = {
                        name: response.name,
                        realname: response.realname,
                        referral_link: response.referral_link,
                        avatar_url: '/default-avatar.png', // Default avatar since controller doesn't provide 'avatar_url'
                    };
    
                    // Build the HTML for the found friend
                    const friendHtml = `
                        <div class="friend-item">
                            <img src="${friend.avatar_url}" alt="${friend.realname}" style="width:50px;height:50px;">
                            <div class="friend-details">
                                <h5>${friend.realname} 【${friend.name}】</h5>
                            </div>
                            <div class="friend-actions">
                                <button class="btn btn-success btn-sm add-friend-button" data-referral-link="${friend.referral_link}">
                                    添加好友
                                </button>
                            </div>
                        </div>
                    `;
                    $('#search-results').html(friendHtml);
                } else {
                    $('#search-results').html(`<p class="text-danger">${response.message}</p>`);
                }
            },
            error: function (xhr) {
                if (xhr.status === 404 || xhr.status === 400) {
                    $('#search-results').html(`<p class="text-danger">${xhr.responseJSON.message}</p>`);
                } else {
                    $('#search-results').html(`<p class="text-danger">搜索失败，请稍后再试。</p>`);
                }
            }
        });
    });
    
    // Handle Add Friend Button Click
    $('#searchAddFriendModal').on('click', '.add-friend-button', function () {
        const referralLink = $(this).data('referral-link');
        const button = $(this);
    
        if (!referralLink) {
            alert('无效的推荐码。');
            return;
        }
    
        // Disable the button to prevent multiple clicks
        button.prop('disabled', true).text('发送中...');
    
        $.ajax({
            url: `/robot/add-friend`, // Ensure this route points to your addFriend controller method
            type: 'POST',
            data: {
                referral_link: referralLink,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken,
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert(response.message);
                    button.remove(); // Remove the add button after successful request
                } else {
                    alert(response.message);
                    button.prop('disabled', false).text('添加好友');
                }
            },
            error: function (xhr) {
                if (xhr.status === 404 || xhr.status === 400) {
                    alert(xhr.responseJSON.message);
                } else {
                    alert('添加好友失败，请稍后再试。');
                }
                button.prop('disabled', false).text('添加好友');
            }
        });
    });
    
    // Memberlist add friend
    $(document).on('click', '.open-member-list', function (e) {
        e.preventDefault();
        const grpchatId = $(this).data('grpchat-id');
        
        // 发起 AJAX 请求以获取成员
        $.ajax({
            url: `/grpchat/${grpchatId}/get-members`, // 根据路由调整 URL
            type: 'GET',
            data: {
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken, // 如果需要
            },
            success: function (response) {
                if (response.members) {
                    let membersHtml = `
                        <ul class="list-group" id="view-members-list">
                            ${response.members.map(member => {
                                // Construct full avatar URL
                                const avatarUrl = `${window.location.origin}/storage/${member.avatar_url}`;
                                
                                console.log('Avatar URL:', avatarUrl); // Debugging
                        
                                const isOwner = member.id === response.owner_id;
                                return `
                                    <li class="list-group-item d-flex align-items-center justify-content-between member-item">
                                        <div class="d-flex align-items-center">
                                            <img 
                                                src="${avatarUrl}" 
                                                alt="${member.realname}" 
                                                class="rounded-circle me-3" 
                                                style="width:40px;height:40px;" 
                                                onerror="this.src='${window.location.origin}/default-avatar.png';"
                                            >
                                            <div>
                                                <strong>${member.nickname} 【${member.realname}】</strong>
                                                ${isOwner ? `<span class="badge bg-primary ms-2">群主</span>` : ''}
                                            </div>
                                        </div>
                                        <button class="btn btn-sm btn-success add-friend-button" data-referral-link="${member.referral_link}" data-grpid="${grpchatId}" title="加好友">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </li>
                                `;
                            }).join('')}
                        </ul>
                    `;
                    $('#member-list-content').html(membersHtml);
                } else if (response.error) {
                    $('#member-list-content').html(`<p class="text-danger">${response.error}</p>`);
                } else {
                    $('#member-list-content').html('<p class="text-danger">无法获取成员列表。</p>');
                }
    
                // 显示模态框
                $('#viewMembersModal').modal('show');
            },
            error: function (xhr) {
                if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.error) {
                    $('#member-list-content').html(`<p class="text-danger">${xhr.responseJSON.error}</p>`);
                } else {
                    $('#member-list-content').html('<p class="text-danger">发生错误，请稍后重试。</p>');
                }
                $('#viewMembersModal').modal('show');
            }
        });
    });
    
    // New Filtering Logic for View Members Modal
    $('#view-member-search').on('input', function () {
        const query = $(this).val().toLowerCase().trim();
        console.log('Search Query:', query); // Debugging
    
        $('#view-members-list .member-item').each(function () {
            const memberInfo = $(this).find('strong').text().toLowerCase().trim();
            const isMatch = memberInfo.includes(query);
            console.log('Member Info:', memberInfo, 'Match:', isMatch); // Debugging
    
            if (isMatch) {
                $(this).removeClass('d-none').addClass('d-flex');
                console.log('Showing:', $(this).text()); // Debugging
            } else {
                $(this).removeClass('d-flex').addClass('d-none');
                console.log('Hiding:', $(this).text()); // Debugging
            }
        });
    });
    
    $(document).on('click', '.add-friend-button', function () {
        const referralLink = $(this).data('referral-link');
        const button = $(this);
        const grpid = $(this).data('grpid');
        console.log('Group ID (grpid):', grpid);
    
        if (!referralLink) {
            alert('无法获取推荐码。');
            return;
        }
    
        // Disable the button to prevent multiple clicks
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 添加中...');
    
        $.ajax({
            url: '/robot/add-friend', // Ensure this matches your route
            type: 'POST',
            data: {
                referral_link: referralLink,
                impersonation_token: window.chatConfig.impersonationToken,
                _token: window.chatConfig.csrfToken,
                grpid: grpid,
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert(response.message || '好友请求已发送成功。');
                    // Optionally, change the button state to indicate success
                    button.removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i> 已发送');
                } else {
                    // Handle specific error messages returned with 200 OK status
                    alert(response.message || '发送好友请求失败。');
                    // Re-enable the button
                    button.prop('disabled', false).html('<i class="fas fa-plus"></i>');
                }
            },
            error: function (xhr) {
                // Initialize a default error message
                let errorMessage = '发送好友请求失败，请稍后再试。';
    
                // Check if the response contains JSON data with a message
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
    
                // Display the specific error message
                alert(errorMessage);
    
                // Re-enable the button
                button.prop('disabled', false).html('<i class="fas fa-plus"></i>');
            }
        });
    });

    //Group invitation
    $(document).ready(function() {
        // Setup CSRF token for all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    
        // Event listener for "Invite Members" button
        $(document).on('click', '.invite-members', function () {
            const grpchatId = $(this).data('grpchat-id');
            const impersonationToken = window.chatConfig.impersonationToken;
        
            // Step 1: Fetch friend list
            $.ajax({
                url: '/robot/friendlist',
                type: 'GET',
                data: { impersonation_token: impersonationToken },
                success: function (friendResponse) {
                    console.log('Friend list response:', friendResponse);
                    const friends = friendResponse.friends || [];
        
                    // Step 2: Fetch group members
                    $.ajax({
                        url: `/grpchat/${grpchatId}/get-members`,
                        type: 'GET',
                        data: { impersonation_token: impersonationToken },
                        success: function (groupResponse) {
                            console.log('Group members response:', groupResponse);
        
                            const currentMembers = Array.isArray(groupResponse.members) 
                                ? groupResponse.members.map(member => member.id) 
                                : [];
        
                            let membersHTML = '';
        
                            friends.forEach(friend => {
                                const isInGroup = currentMembers.includes(friend.id);
        
                                membersHTML += `
                                    <div class="friend-item d-flex align-items-center mb-3 p-2 border rounded">
                                        <img src="${friend.avatar_url || '/default-avatar.png'}" alt="Avatar" class="rounded-circle me-3" style="width: 50px; height: 50px;">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">${friend.nickname} 【${friend.realname}】</h6>
                                            ${
                                                isInGroup
                                                    ? `<button class="btn btn-secondary btn-sm" disabled>已在群</button>`
                                                    : `<button class="btn btn-success btn-sm invite-to-group" 
                                                        data-grpchat-id="${grpchatId}" 
                                                        data-member-id="${friend.id}">
                                                        邀请
                                                      </button>`
                                            }
                                        </div>
                                    </div>`;
                            });
        
                            $('#invite-friend-list-content').html(membersHTML);
                            $('#inviteMembersModal').modal('show');
                        },
                        error: function (xhr, status, error) {
                            console.error('Error fetching group members:', error);
                            alert('无法获取群组成员列表。请再试一次。');
                        }
                    });
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching friend list:', error);
                    alert('无法获取好友名单。请再试一次。');
                }
            });
        });
        
        // New Filtering Logic for Invite Members Modal
        $('#invite-member-search').on('input', function () {
            const query = $(this).val().toLowerCase().trim();
        
            $('#invite-friend-list-content .friend-item').each(function () {
                const memberInfo = $(this).find('h6.mb-1').text().toLowerCase().trim();
                const isMatch = memberInfo.includes(query);
        
                if (isMatch) {
                    $(this).removeClass('d-none').addClass('d-flex');
                } else {
                    $(this).removeClass('d-flex').addClass('d-none');
                }
            });
        });

    
        // Handle Invite Button Click
        $(document).on('click', '.invite-to-group', function () {
            const grpchatId = $(this).data('grpchat-id');
            const memberId = $(this).data('member-id');
            const impersonationToken = window.chatConfig.impersonationToken;
    
            $.ajax({
                url: `/grpchat/${grpchatId}/add-member`, // Ensure this URL matches your route
                type: 'POST',
                data: {
                    member_id: memberId,
                    impersonation_token: impersonationToken,
                    _token: window.chatConfig.csrfToken,
                },
                success: function (response) {
                    alert(response.message || '成员已成功邀请到群组。');
    
                    // Change the button to "已在群"
                    $(`[data-member-id="${memberId}"]`)
                        .removeClass('btn-success invite-to-group')
                        .addClass('btn-secondary')
                        .text('已在群')
                        .prop('disabled', true);
                },
                error: function (xhr, status, error) {
                    const response = xhr.responseJSON || {};
                    alert(response.message || '邀请成员失败，请稍后再试。');
                }
            });
        });
    });
    
    //Show incoming count
    function updateFriendRequestCount() {
        const impersonationToken = window.chatConfig.impersonationToken;
    
        $.ajax({
            url: '/robot/incoming-requests',
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            success: function (response) {
                if (response.status === 'success') {
                    const requests = response.incoming_requests;
                    const requestCount = requests.length;
    
                    // Update the button dynamically
                    const button = $('#friend-requests-button');
                    if (requestCount > 0) {
                        button.html(`好友申请&nbsp;<span class="badge bg-danger">${requestCount}</span>`);
                    } else {
                        button.html(`好友申请`);
                    }
                }
            },
            error: function () {
                console.error('无法获取好友申请数。');
            }
        });
    }
    
   // Define the drop zone
    const dropZone = $('.chat-box-inner');

    // Prevent default behavior for dragover and drop events
    dropZone.on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragging'); // Add class for visual feedback
    });

    dropZone.on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragging');
    });

    dropZone.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragging');

        const files = e.originalEvent.dataTransfer.files;
        handleDroppedFiles(files);
    });

    // Function to handle dropped files
    function handleDroppedFiles(files) {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                showImagePreview(file);
            } else {
                alert('只支持图片文件上传。'); // "Only image files are supported for upload."
            }
        }
    }
    
    const messageInput = $('#message-input');

    messageInput.on('paste', function (e) {
        const items = (e.originalEvent.clipboardData || window.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const blob = items[i].getAsFile();
                if (blob) {
                    showImagePreview(blob);
                }
            }
        }
    });
    
    function showImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            $('#image-preview').attr('src', e.target.result);
            $('#image-preview-container').data('file', file).fadeIn();
        };
        reader.readAsDataURL(file);
    }

    // Handle Send Preview Button Click
    $('#send-preview-btn').on('click', function () {
        const file = $('#image-preview-container').data('file');
        if (file) {
            uploadFile(file, 'image');
            hideImagePreview();
        }
    });

    // Handle Cancel Preview Button Click
    $('#cancel-preview-btn, .close-preview').on('click', function () {
        hideImagePreview();
    });

    // Function to hide image preview
    function hideImagePreview() {
        $('#image-preview-container').fadeOut().data('file', null);
        $('#image-preview').attr('src', '#');
    }
    
    $(document).on('click', '.chat-image', function () {
        const imageUrl = $(this).attr('src');
        const modalHtml = `
            <div id="imageModal" class="modal">
                <div class="modal-overlay"></div>
                <span class="close">&times;</span>
                <div class="modal-content-wrapper">
                    <img class="modal-content" id="modalImage">
                    <div class="zoom-controls">
                        <button id="zoomIn">放大</button>
                        <button id="zoomOut">缩小</button>
                        <button id="rotate">转动</button>
                    </div>
                </div>
            </div>
        `;
    
        // Append modal to body if it doesn't already exist
        if ($('#imageModal').length === 0) {
            $('body').append(modalHtml);
        }
    
        // Show the modal
        const modal = $('#imageModal');
        modal.show();
    
        // Set the image source
        $('#modalImage').attr('src', imageUrl);
    
        let zoomLevel = 1; // Initial zoom level
        let rotationAngle = 0; // Initial rotation angle
    
        // Zoom In Button
        $('#zoomIn').off('click').on('click', function () {
            zoomLevel += 0.1;
            $('#modalImage').css('transform', `scale(${zoomLevel}) rotate(${rotationAngle}deg)`);
        });
    
        // Zoom Out Button
        $('#zoomOut').off('click').on('click', function () {
            if (zoomLevel > 0.1) {
                zoomLevel -= 0.1;
                $('#modalImage').css('transform', `scale(${zoomLevel}) rotate(${rotationAngle}deg)`);
            }
        });
    
        // Rotate Button
        $('#rotate').off('click').on('click', function () {
            rotationAngle += 45; // Rotate 45 degrees
            $('#modalImage').css('transform', `scale(${zoomLevel}) rotate(${rotationAngle}deg)`);
        });
    
        // Close the modal when the user clicks the close button or outside the image
        $('.close').on('click', function () {
            modal.hide();
        });
    
        modal.on('click', function (e) {
            if (e.target.id === 'imageModal' || $(e.target).hasClass('modal-overlay')) {
                modal.hide();
            }
        });
    });
    
    //Chat Avatar preview
    $(document).on('click', '.avatar', function () {
        const avatarUrl = $(this).attr('src');
        const modalHtml = `
            <div id="avatarModal" class="modal">
                <div class="modal-overlay"></div>
                <span class="close">&times;</span>
                <div class="modal-content-wrapper">
                    <img class="modal-content" id="modalAvatar">
                    <div class="zoom-controls">
                        <button id="zoomInAvatar">放大</button>
                        <button id="zoomOutAvatar">缩小</button>
                    </div>
                </div>
            </div>
        `;
    
        // Append modal to body if it doesn't already exist
        if ($('#avatarModal').length === 0) {
            $('body').append(modalHtml);
        }
    
        // Show the modal
        const modal = $('#avatarModal');
        modal.show();
    
        // Set the avatar source
        $('#modalAvatar').attr('src', avatarUrl);
    
        let zoomLevel = 1; // Initial zoom level
    
        // Zoom In Button
        $('#zoomInAvatar').off('click').on('click', function () {
            zoomLevel += 0.1;
            $('#modalAvatar').css('transform', `scale(${zoomLevel})`);
        });
    
        // Zoom Out Button
        $('#zoomOutAvatar').off('click').on('click', function () {
            if (zoomLevel > 0.1) {
                zoomLevel -= 0.1;
                $('#modalAvatar').css('transform', `scale(${zoomLevel})`);
            }
        });
    
        // Close the modal when the user clicks the close button or outside the image
        $('.close').on('click', function () {
            modal.hide();
        });
    
        modal.on('click', function (e) {
            if (e.target.id === 'avatarModal' || $(e.target).hasClass('modal-overlay')) {
                modal.hide();
            }
        });
    });
    
    // Initialize EmojiButton
    const picker = new EmojiButton({
        position: 'top-end',
        autoHide: false,
    });

    const emojiButton = document.querySelector('#emoji-button');
    const messageInput2 = document.querySelector('#message-input');

    if (emojiButton && messageInput2) {
        picker.on('emoji', selection => {
            console.log('Selected Emoji:', selection.emoji);
            insertAtCursor(messageInput2, selection.emoji);
        });

        emojiButton.addEventListener('click', () => {
            console.log("Emoji button clicked");
            picker.togglePicker(emojiButton);
        });
    } else {
        console.error('Emoji button or message input not found in the DOM.');
    }

    function insertAtCursor(input, text) {
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const value = input.value;
        input.value = value.substring(0, start) + text + value.substring(end);
        input.selectionStart = input.selectionEnd = start + text.length;
        input.focus();
    }
    
    // Function to update the chat header
    function updateChatHeader(response) {
        const currentUserId = Number(response.messages.user_id);
        
        const chatHeader = $('.chat-box-header');
        const leftGroup = chatHeader.find('.left-group');
        leftGroup.empty();
        
        if (response.chat_info.type === 'grpchat') {
            const avatar = response.chat_info.avatar 
                ? `<img src="/storage/${response.chat_info.avatar}" alt="Group Avatar" class="avatar">` 
                : `<div class="avatar-default"></div>`;
            const chatName = response.chat_info.chatname || '群组';
            const memberCount = response.chat_info.member_count || 0;
            const owner = response.chat_info.owner_name || '管理员';
            const groupId = response.chat_info.id || response.chat_info.group_id; // Adjust based on actual field
            
            leftGroup.append(`
                <div class="avatar-wrapper">
                    ${avatar}
                </div>
                <div class="info-wrapper">
                    <span class="user-name-grp" data-user='${JSON.stringify({
                        id: groupId,
                        realname: chatName,
                        nickname: chatName
                    })}'>${chatName}</span>
                    <span class="time">成员数: ${memberCount}</span>
                </div>
            `);
        } else if (response.chat_info.type === 'conversation') {
            const avatar = response.chat_info.avatar 
                ? `<img src="/storage/${response.chat_info.avatar}" alt="Avatar" class="avatar">` 
                : `<div class="avatar-default"></div>`;
            
            // Prioritize chat_info.nickname over realname
            const displayName = (response.chat_info.nickname && response.chat_info.nickname !== 'none') 
                ? response.chat_info.nickname 
                : response.chat_info.realname;
            
            // Function to check if the user is online based on last_online timestamp
            function isOnline(lastOnline) {
                const lastOnlineDate = new Date(lastOnline);
                const now = new Date();
                const timeDiff = now - lastOnlineDate;
                const onlineThreshold = 5 * 60 * 1000; // 5 minutes in milliseconds
            
                return timeDiff <= onlineThreshold;
            }
            
            // Function to format the time in the desired format
            function formatTime(lastOnline) {
                const date = new Date(lastOnline);
            
                const hours = date.getHours();
                const minutes = date.getMinutes();
                const formattedHours = hours % 12 || 12; // Convert to 12-hour format
                const ampm = hours >= 12 ? 'PM' : 'AM';
            
                const day = date.getDate();
                const month = date.getMonth() + 1; // Months are 0-based
                const year = String(date.getFullYear()).slice(-2); // Last two digits of the year
            
                return `${formattedHours}:${minutes.toString().padStart(2, '0')} ${ampm} ${day}/${month}/${year}`;
            }
            
            const onlineStatus = isOnline(response.chat_info.last_online)
            ? `在线：${formatTime(response.chat_info.last_online)}`
            : `最后上线：${formatTime(response.chat_info.last_online)}`;
            
            // Ensure chat_info.id is present
            const otherUserId = response.chat_info.id || response.chat_info.user_id; // Adjust based on actual field
            const otherUserName = response.chat_info.realname;
            const otherUserAge = response.chat_info.age;
            const currentChatId = response.chat_info.conversation_id;
            
            const otherUserNickname = (response.chat_info.nickname && response.chat_info.nickname !== 'none') 
                ? response.chat_info.nickname 
                : otherUserName;
            
            leftGroup.append(`
                <div class="avatar-wrapper">
                    ${avatar}
                </div>
                <div class="info-wrapper">
                    <span class="user-name" data-user='${JSON.stringify({
                        id: otherUserId,
                        name: otherUserName,
                        nickname: otherUserNickname,
                        age: otherUserAge,
                        chatId: currentChatId
                    })}'>${otherUserNickname}</span>
                    <span class="time">${onlineStatus}</span>
                </div>
            `);
        }
        chatHeader.show();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const settingsButton = document.getElementById('settings-button');
    const conversationOverlay = document.getElementById('conversation-settings-overlay');
    const grpchatOverlay = document.getElementById('grpchat-settings-overlay');
    const closeOverlayButtons = document.querySelectorAll('.close-overlay');
    const pinChatToggle = document.getElementById('pin-chat-toggle');
    const pinGrpChatToggle = document.getElementById('pin-grpchat-toggle');
    const remarkButton = document.getElementById('remark-btn');
    const deleteFriendBtn = document.getElementById('delete-friend-btn');
    const quitGroupBtn = document.getElementById('quit-group-btn');
    const popoutSave = document.getElementById('popout-save');
    const popoutClose = document.getElementById('popout-close');
    const popout = document.getElementById('miniPopout');
    const categoriesButton = document.getElementById('create-category');
    const groupSettingsButton = document.getElementById('category-btn');
    const qrButton = document.getElementById('my-qr-code');
    const historyButton = document.getElementById('history-button');
    
    let currentChatType = null;
    
    function closeAllOverlays() {
        if (conversationOverlay) {
            conversationOverlay.classList.add('d-none');
        } else {
            console.error('conversationOverlay is not defined or not found in the DOM');
        }
    
        if (grpchatOverlay) {
            grpchatOverlay.classList.add('d-none');
        } else {
            console.error('grpchatOverlay is not defined or not found in the DOM');
        }
    }

    // Close overlays when close button is clicked
    closeOverlayButtons.forEach(button => {
        button.addEventListener('click', closeAllOverlays);
    });

    if (settingsButton) {
        settingsButton.addEventListener('click', function () {
            if (currentChatType === 'conversation') {
                if (conversationOverlay) {
                    conversationOverlay.classList.remove('d-none');
                } else {
                    console.error('conversationOverlay not found in the DOM');
                }

                if (grpchatOverlay) {
                    grpchatOverlay.classList.add('d-none');
                }
            } else if (currentChatType === 'grpchat') {
                if (grpchatOverlay) {
                    grpchatOverlay.classList.remove('d-none');
                } else {
                    console.error('grpchatOverlay not found in the DOM');
                }

                if (conversationOverlay) {
                    conversationOverlay.classList.add('d-none');
                }

                // Populate grpchatOverlay with currentGrpchat data
                if (currentGrpchat) {
                    if (groupSettingsButton) {
                        if (currentGrpchat.admins.includes(user.id)) {
                            // Show admin-specific settings
                            groupSettingsButton.style.display = 'block';
                        } else {
                            groupSettingsButton.style.display = 'none';
                        }
                    } else {
                        console.error('groupSettingsButton not found in the DOM');
                    }
                }
            }
        });
    } else {
        console.error('settingsButton not found in the DOM');
    }
    
    // Handle chat selection
    const people = document.querySelectorAll('.person');
    people.forEach(person => {
        person.addEventListener('click', function () {
            const chatType = this.getAttribute('data-chat-type');
            currentChatType = chatType;
            
            // Close all overlays, including the history modal
            closeAllOverlays();
            if (historyModal) {
                historyModal.classList.add('d-none');
            }
            
            if (chatType === 'grpchat') {
                // Fetch group chat data from data attributes or make an AJAX call
                currentGrpchat = {
                    id: this.getAttribute('data-chat-id'),
                    admins: JSON.parse(this.getAttribute('data-admins') || '[]'),
                    // Add other grpchat properties as needed
                };
            } else if (chatType === 'conversation') {
                // Fetch conversation chat id or other data as needed
                currentConversation = {
                    id: this.getAttribute('data-chat-id'),
                    // Add other conversation properties as needed
                };
            }
        });
    });

    
    if (pinChatToggle) {
        pinChatToggle.addEventListener('change', function () {
            const isPinned = pinChatToggle.checked;
            console.log('Conversation pin state changed:', isPinned);
            // Add your logic for pinning conversation
        });
    }

    // Attach event listener for pinGrpChatToggle if it exists
    if (pinGrpChatToggle) {
        pinGrpChatToggle.addEventListener('change', function () {
            const isPinned = pinGrpChatToggle.checked;
            console.log('Group chat pin state changed:', isPinned);
            // Add your logic for pinning group chat
        });
    }
    
    // Handle delete friend button
    if (deleteFriendBtn) {
        deleteFriendBtn.addEventListener('click', function () {
            // Add your logic to delete friend
            closeAllOverlays(); // Optionally close overlays after action
        });
    }

    // Handle quit group button
    if (quitGroupBtn) {
        quitGroupBtn.addEventListener('click', function () {
            // Add your logic to quit group chat
            closeAllOverlays(); // Optionally close overlays after action
        });
    }

    // Event delegation for handling user-name and remark-btn clicks
    document.body.addEventListener('click', function(event) {
        const target = event.target;

        // Handle user-name click
        if (target.classList.contains('user-name')) {
            try {
                const userData = JSON.parse(target.getAttribute('data-user'));
                const bubbleAvatar = target.closest('.bubble')?.querySelector('.bubbleavatar')?.src || '';
                showPopout(userData, bubbleAvatar, target);
                closeAllOverlays(); // Close overlays when popout is shown
            } catch (error) {
                console.error('Error parsing data-user attribute for user-name:', error);
            }
        }

        // Handle "备注" button click
        if (target.id === 'remark-btn') {
            try {
                const userData = JSON.parse(target.getAttribute('data-user'));
                const bubbleAvatar = document.querySelector('.bubbleavatar')?.src || '';
                showPopout(userData, bubbleAvatar, target);
                closeAllOverlays(); // Close overlays when popout is shown
            } catch (error) {
                console.error('Error parsing data-user attribute for remark button:', error);
            }
        }
    
        // Close popout if clicking outside
        if (!target.closest('#miniPopout') && !target.classList.contains('user-name') && target.id !== 'remark-btn') {
            closePopout();
        }
    });

    // Define the function to show the popout
    function showPopout(userData, avatarUrl, target) {
        console.log('showPopout userData:', userData);
        const popoutAvatar = document.getElementById('popout-avatar');
        const popoutName = document.getElementById('popout-name');
        const popoutNickname = document.getElementById('popout-nickname');
        const popoutInput = document.getElementById('popout-input');
        const popoutAge = document.getElementById('popout-age');
        const popout = document.getElementById('miniPopout');
        
        // Set a default avatar if none is provided
        if (!avatarUrl || avatarUrl.trim() === "") {
            avatarUrl = `${window.location.origin}/default-avatar.png`;
        }
        
        // Update avatar
        popoutAvatar.src = avatarUrl;
        
        // Update name and nickname
        const displayName = userData.name || "未命名";
        let nicknameText = userData.nickname || "未备注";
        let ageText = userData.age || "未备注";
        
        popoutName.textContent = displayName;
        popoutNickname.textContent = `【${nicknameText}】`;
        popoutAge.textContent = `${ageText}`;
        popoutName.setAttribute('data-target-id', userData.id);
        popoutName.setAttribute('data-chat-id', userData.chatId);
    
        // Update input value and placeholder
        popoutInput.value = userData.remark || '';
        popoutInput.placeholder = nicknameText === "未备注" ? "放个备注吧..." : nicknameText;
    
        // Show the popout
        popout.classList.remove('d-none');
    }

    // Define the function to close the popout
    function closePopout() {
        if (!popout.classList.contains('d-none')) {
            popout.classList.add('d-none');
        }
    }
    
    // Save popout remark
    popoutSave.addEventListener('click', () => {
        const impersonationToken = window.chatConfig?.impersonationToken || '';
        const nickname = document.getElementById('popout-input').value.trim();
        const targetId = document.getElementById('popout-name').getAttribute('data-target-id');

        if (!nickname || !targetId) {
            alert('请填写备注昵称。');
            return;
        }

        fetch('/robot/remark', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                impersonation_token: impersonationToken,
                target_id: targetId,
                nickname: nickname,
            }),
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw err; });
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    alert('备注保存成功。');
                    updateUserNameInDOM(parseInt(targetId), nickname);
                } else {
                    alert('保存备注失败。');
                }
            })
            .catch(error => {
                console.error('Error saving remark:', error);
                alert('保存备注时发生错误。');
            });

        document.getElementById('popout-input').value = '';
        closePopout();
    });

    // Handle popout cancel button
    popoutClose.addEventListener('click', () => {
        closePopout();
    });

    // Function to update the user name in the DOM after saving the remark
    function updateUserNameInDOM(targetId, newNickname) {
        const numericTargetId = Number(targetId);
        if (isNaN(numericTargetId)) {
            console.warn('[updateUserNameInDOM] Invalid targetId provided:', targetId);
            return;
        }
    
        const userNameElements = document.querySelectorAll('.user-name');
    
        userNameElements.forEach(element => {
            try {
                const userDataAttribute = element.getAttribute('data-user');
                if (!userDataAttribute) {
                    return;
                }

                const userData = JSON.parse(userDataAttribute);

                if (userData.id === numericTargetId) {
                    // Update both realname and nickname to newNickname
                    userData.realname = newNickname;
                    userData.nickname = newNickname;

                    // Update the DOM element
                    element.textContent = newNickname;
                    element.setAttribute('data-user', JSON.stringify(userData));
                }
            } catch (e) {
                console.error('Error updating user name in DOM:', e, element);
            }
        });
    }
     
    // Right Click Bubble - Context Menu
    const contextMenu = document.createElement('div');
    contextMenu.id = 'contextMenu';
    contextMenu.style.cssText = `
        display: none;
        position: absolute;
        background-color: white;
        border: 1px solid #ccc;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        padding: 5px 0;
        width: 100px;
        border-radius: 4px;
        font-family: Arial, sans-serif;
    `;
    contextMenu.innerHTML = `
        <ul style="list-style:none; margin:0; padding:0;">
            <li id="copy" style="padding:8px 12px; cursor:pointer;">复制</li>
            <li class="recall-button" style="padding:8px 12px; cursor:pointer;">删除</li>
            <li class="reply-button" style="padding:8px 12px; cursor:pointer;">回复</li>
        </ul>
    `;
    document.body.appendChild(contextMenu);
    
    // Show Context Menu on right-click
    document.addEventListener('contextmenu', (event) => {
        const bubble = event.target.closest('.bubble');
        if (bubble) {
            event.preventDefault();
            selectedBubble = bubble;
            contextMenu.style.left = `${event.pageX}px`;
            contextMenu.style.top = `${event.pageY}px`;
            contextMenu.style.display = 'block';
            
            console.log('Context menu displayed for bubble:', bubble);
            console.log('Selected Bubble Data:', {
                messageId: bubble.getAttribute('data-message-id'),
                isGroupChat: bubble.getAttribute('data-is-group-chat'),
                senderName: bubble.getAttribute('data-sender-name')
            });
        } else {
            contextMenu.style.display = 'none';
            selectedBubble = null;
        }
    });
    
    // Hide Context Menu on click elsewhere, except when clicking inside the context menu
    document.addEventListener('click', (event) => {
        if (!contextMenu.contains(event.target)) {
            contextMenu.style.display = 'none';
            selectedBubble = null; // Clear the selected bubble when clicking outside the context menu
        }
    });
    
    // Handle Copy Action Separately
    contextMenu.addEventListener('click', (event) => {
        if (event.target.id === 'copy' && selectedBubble) {
            const textElement = selectedBubble.querySelector('.preserve-whitespace');
            const text = textElement ? textElement.innerText.trim() : selectedBubble.innerText.trim();
            navigator.clipboard.writeText(text)
                .then(() => {
                    console.log('Copied text to clipboard:', text);
                })
                .catch(err => {
                    console.error('复制失败:', err);
                });
            contextMenu.style.display = 'none';
        }
        // "删除" and "回复" are handled by their respective event handlers
    });
    
    // Event listener to show the modal
    categoriesButton.addEventListener('click', function () {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createCategoryModalLabel">请输入分组的名字</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="text" id="category-name" class="form-control" placeholder="输入分组名字">
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="confirm-create" class="btn btn-primary">确认</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const createCategoryModal = new bootstrap.Modal(document.getElementById('createCategoryModal'));
        createCategoryModal.show();

        document.getElementById('confirm-create').addEventListener('click', function () {
            const categoryName = document.getElementById('category-name').value;

            if (categoryName.trim() === '') {
                alert('分组名字不能为空！');
                return;
            }

            // Send AJAX request to create the category
            fetch('/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.chatConfig.csrfToken,
                },
                body: JSON.stringify({
                    name: categoryName,
                    _token: window.chatConfig.csrfToken,
                    impersonation_token: window.chatConfig.impersonationToken,
                }),
            })
            .then(response => {
                if (response.ok) {
                    alert('分组创建成功！');
                    createCategoryModal.hide();
                    location.reload();
                } else {
                    alert('分组创建失败，请稍后再试。');
                }
            })
            .catch(() => alert('请求失败，请稍后再试。'));
        });
    });
    
    groupSettingsButton.addEventListener('click', async function () {
        // Parse user data from the button's data attribute
        const targetUser = JSON.parse(this.getAttribute('data-user'));
    
        if (!targetUser || !targetUser.id) {
            console.error('Target user not defined or invalid.');
            return;
        }
    
        try {
            // Construct the URL with impersonation_token as a query parameter
            const url = new URL('/fetchCategories', window.location.origin);
            url.searchParams.append('impersonation_token', window.chatConfig.impersonationToken);
    
            // Fetch categories for the current user using GET
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.chatConfig.csrfToken, // Optional for GET
                },
            });
    
            if (!response.ok) {
                throw new Error('Failed to fetch categories');
            }
    
            const categories = await response.json();
    
            // Create the modal HTML
            const modalHtml = `
                <div class="modal fade" id="categorySettingsModal" tabindex="-1" aria-labelledby="categorySettingsModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="categorySettingsModalLabel">分组设置</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="category-dropdown">选择分组:</label>
                                <select id="category-dropdown" class="form-select">
                                    ${categories.map(category => `
                                        <option value="${category.id}" ${category.member_id && category.member_id.includes(targetUser.id) ? 'selected' : ''}>
                                            ${category.name}
                                        </option>`).join('')}
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="confirm-group-settings" class="btn btn-primary">确认</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
    
            // Check if the modal already exists to prevent duplicates
            if (!document.getElementById('categorySettingsModal')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
    
            const categorySettingsModalElement = document.getElementById('categorySettingsModal');
            const categorySettingsModal = new bootstrap.Modal(categorySettingsModalElement);
            categorySettingsModal.show();
    
            // Handle confirm button click
            document.getElementById('confirm-group-settings').addEventListener('click', async function () {
                const selectedCategoryId = document.getElementById('category-dropdown').value;
    
                try {
                    // Send AJAX request to add the user to the category using POST
                    const addMemberResponse = await fetch(`/categories/${selectedCategoryId}/add-member`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.chatConfig.csrfToken,
                        },
                        body: JSON.stringify({
                            user_id: targetUser.id,
                            _token: window.chatConfig.csrfToken,
                            impersonation_token: window.chatConfig.impersonationToken,
                        }),
                    });
    
                    if (addMemberResponse.ok) {
                        alert('分组设置成功！');
                        categorySettingsModal.hide();
                        location.reload(); // Reload to reflect changes
                    } else {
                        let errorMessage = '分组设置失败，请稍后再试。';
                        try {
                            const errorData = await addMemberResponse.json();
                            if (errorData.message) {
                                errorMessage = `分组设置失败: ${errorData.message}`;
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        alert(errorMessage);
                    }
                } catch (error) {
                    console.error('Error adding member to category:', error);
                    alert('请求失败，请稍后再试。');
                }
            });
    
            // Clean up the modal from the DOM after it's hidden to prevent duplicates
            categorySettingsModalElement.addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
    
        } catch (error) {
            console.error('Error fetching categories:', error);
            alert('无法加载分组数据，请稍后再试。');
        }
    });

    qrButton.addEventListener('click', function () {
        const data = {
            impersonation_token: window.chatConfig.impersonationToken,
            _token: window.chatConfig.csrfToken,
        };

        fetch('/robot/my-qr', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            body: JSON.stringify(data),
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const qrCodeContainer = document.getElementById('qrCodeContainer');
                qrCodeContainer.innerHTML = `<img src="${data.qr_code_url}" alt="QR Code" class="img-fluid">`;

                const modalEl = document.getElementById('myQRCodeModal');
                const modalInstance = new bootstrap.Modal(modalEl);
                
                // Listen for when the modal is fully hidden and dispose the instance.
                modalEl.addEventListener('hidden.bs.modal', function () {
                    modalInstance.dispose();
                }, { once: true });
                
                modalInstance.show();
            } else {
                console.error('Failed to fetch QR Code:', data.error);
            }
        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
        });
    });
    
    // --- History Section Integration ---

    // References to the history modal and its close button
    const historyModal = document.getElementById('history-modal');
    const closeHistoryModal = document.getElementById('close-history-modal');
    
    historyButton.addEventListener("click", function () {
        // Log the current chat type for debugging
        console.log("History Button Clicked");
        console.log("Current Chat Type:", currentChatType);
        
        // Determine the current chat id and type
        let chatId, chatType;
        if (currentChatType === 'grpchat' && typeof currentGrpchat !== 'undefined') {
            chatId = currentGrpchat.id;
            chatType = 'grpchat';
            console.log("Current Group Chat ID:", chatId);
        } else if (currentChatType === 'conversation' && typeof currentConversation !== 'undefined') {
            chatId = currentConversation.id;
            chatType = 'conversation';
            console.log("Current Conversation ID:", chatId);
        } else {
            console.error("No valid chat selected");
            return;
        }
        
        // Show the history modal
        historyModal.classList.remove('d-none');
        
        // Fetch and display the history media for the current chat
        getHistoryMedia(chatId, chatType);
    });
    
    closeHistoryModal.addEventListener("click", function () {
        historyModal.classList.add('d-none');
    });
    
    // Tab switching functionality for the history modal
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener("click", function () {
            // Remove active class from all tab buttons and contents
            tabButtons.forEach(btn => btn.classList.remove("active"));
            tabContents.forEach(content => content.classList.remove("active"));
        
            // Activate the clicked tab and its corresponding content
            button.classList.add("active");
            const targetTab = button.getAttribute("data-target");
            document.getElementById(targetTab).classList.add("active");
            
            // Log which tab was clicked for debugging
            console.log("Tab Button Clicked:", targetTab);
        });
    });
    
    // --- AJAX Call to Fetch History Media ---
    
    /**
     * Fetch history media (images, videos, docs) from the server and update the UI.
     * @param {string|number} chatId - The id of the current chat.
     * @param {string} chatType - Either 'grpchat' or 'conversation'.
     */
    function getHistoryMedia(chatId, chatType) {
        // Construct the URL for the AJAX request.
        // For example: /robot/grpchats/17/history?chat_type=grpchat
        let url = `/robot/grpchats/${chatId}/history?chat_type=${chatType}`;
        
        fetch(url)
          .then(response => response.json())
          .then(data => {
              console.log('History media data:', data);
              
              // Update each tab with its corresponding media items.
              updateTabContent('photos-tab', data.images, 'image');
              updateTabContent('videos-tab', data.videos, 'video');
              updateTabContent('files-tab', data.docs, 'doc');
          })
          .catch(error => {
              console.error('Error fetching history media:', error);
          });
    }
    
    /**
     * Update the content of a given tab with media items.
     * @param {string} tabId - The id of the tab content element.
     * @param {Array} items - An array of media items returned from the server.
     * @param {string} type - The type of media: 'image', 'video', or 'doc'.
     */
    function updateTabContent(tabId, items, type) {
        // Find the tab element and the container for the media items.
        const tabElement = document.getElementById(tabId);
        // Assume that each tab content has a child element with the class "items-grid"
        const container = tabElement.querySelector('.items-grid');
        
        if (!container) {
            console.error("Container (.items-grid) not found in tab:", tabId);
            return;
        }
        
        // Clear any existing content
        container.innerHTML = '';
        
        // Loop through each media item and create a thumbnail element
        items.forEach(item => {
            let el = document.createElement('div');
            el.classList.add('media-item');
            
            if (type === 'image') {
                let img = document.createElement('img');
                // Prepend "/storage" if not already present.
                let imageUrl = item.image_url;
                if (!imageUrl.startsWith('/storage')) {
                    imageUrl = '/storage/' + imageUrl;
                }
                img.src = imageUrl;
                img.alt = 'Image';
                img.classList.add('thumbnail');
                el.appendChild(img);
            } else if (type === 'video') {
                // For video, you might show a placeholder icon or a video thumbnail if available
                let videoThumb = document.createElement('div');
                videoThumb.classList.add('video-thumb');
                videoThumb.innerText = 'Video';  // Replace with an icon or thumbnail as needed
                el.appendChild(videoThumb);
            } else if (type === 'doc') {
                // For documents, display a document icon or text
                let docThumb = document.createElement('div');
                docThumb.classList.add('doc-thumb');
                docThumb.innerText = 'Document';  // Replace with an icon if available
                el.appendChild(docThumb);
            }
            
            container.appendChild(el);
        });
    }


});

document.addEventListener('DOMContentLoaded', function () {
    // Cache Original Content
    const originalUserListBoxContent = document.querySelector('.user-list-box').innerHTML;
    const originalChatBoxContent = document.querySelector('.chat-box-inner').innerHTML;

    // Get Tab Elements
    const messagesTab = document.getElementById('messages-tab');
    const contactsTab = document.getElementById('contacts-tab');

    // Debug: Check if Elements Exist
    if (!messagesTab) {
        console.error('Element with ID "messages-tab" not found!');
    } else {
        console.log('Element "messages-tab" found:', messagesTab);
    }

    if (!contactsTab) {
        console.error('Element with ID "contacts-tab" not found!');
    } else {
        console.log('Element "contacts-tab" found:', contactsTab);
    }

    // Add Click Event Listener to **通讯录**
    contactsTab.addEventListener('click', function () {
        console.log('通讯录 clicked!');

        // Populate the user list box with the menu without "查看分组" button
        const userListBox = document.querySelector('.user-list-box');
        if (userListBox) {
            // Updated list content without "查看分组"
            userListBox.innerHTML = `
                <ul class="contacts-menu">
                    <li id="c-new-friends"><i class="fas fa-user-plus"></i> 新的好友</li>
                    <li id="c-my-friends"><i class="fas fa-user"></i> 我的好友</li>
                    <!--<li id="c-my-groups"><i class="fas fa-user-friends"></i> 我的群组</li>
                    <li id="c-joined-groups"><i class="fas fa-users"></i> 我加入的群组</li>-->
                </ul>
                <div id="categories-display" class="mt-3">
                </div>
            `;

            // Automatically load and display categories
        const impersonationToken = window.chatConfig.impersonationToken;
        
        $.ajax({
            url: '/robot/view-categories',
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            headers: {
                'Impersonation-Token': impersonationToken,
            },
            success: function (response) {
                console.log("View Categories Response:", response); // Debugging
        
                const categoriesDisplay = document.getElementById('categories-display');
                if (!categoriesDisplay) {
                    console.error('#categories-display element not found!');
                    return;
                }
        
                if (response.categories && Array.isArray(response.categories) && response.categories.length > 0) {
                    let contentHtml = `
                        <h6 class="m-3">我的分组</h5> <!-- Title -->
                        <ul class="list-group">
                    `;
        
                    response.categories.forEach(category => {
                        const categoryId = `category-${category.id}`;
                        contentHtml += `
                            <li class="list-group-item">
                                <strong>${category.name}</strong>
                                <button class="btn btn-link btn-sm text-decoration-none float-end" 
                                    data-bs-toggle="collapse" data-bs-target="#${categoryId}">
                                    展开/收起
                                </button>
                                <ul id="${categoryId}" class="list-unstyled collapse">
                                    ${
                                        category.members.length > 0
                                            ? category.members.map(member => {
                                                  // Ensure all required fields are present
                                                  const enrichedMember = {
                                                      ...member,
                                                      name: member.realname || "未命名", // Use `realname` as `name` if available
                                                      nickname: member.nickname || "未备注",
                                                      age: member.age || "未备注",
                                                      chatId: member.chatId || category.conversation_id || null, // Derive chatId from category if needed
                                                  };
                                                  
                                                  console.log('Enriched member data:', enrichedMember);
                    
                                                  const avatarUrl = member.avatar
                                                      ? `${window.location.origin}/storage/${member.avatar}`
                                                      : `${window.location.origin}/default-avatar.png`;
                    
                                                  return `
                                                      <li class="align-items-center mb-2">
                                                          <img src="${avatarUrl}" alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                                          <span class="user-name" data-user='${JSON.stringify(enrichedMember)}'>
                                                              ${enrichedMember.realname}
                                                          </span>
                                                      </li>`;
                                              }).join('')
                                            : '<li>没有成员</li>'
                                    }
                                </ul>
                            </li>`;
                    });

        
                    contentHtml += '</ul>';
                    categoriesDisplay.innerHTML = contentHtml;
                } else {
                    categoriesDisplay.innerHTML = '<p class="text-center">没有分组。</p>';
                }
            },
            error: function () {
                document.getElementById('categories-display').innerHTML = '<p class="text-center text-danger">无法获取分组数据，请稍后再试。</p>';
                alert('无法获取分组数据，请稍后再试。');
            },
        });

            
        } else {
            console.error('.user-list-box element not found!');
        }

        // Clear the chat box content
        const chatBoxInner = document.querySelector('.chat-box-inner');
        if (chatBoxInner) {
            chatBoxInner.innerHTML = `<div class="chat-not-selected"><p>选择对话</p></div>`;
            console.log('chat-box-inner cleared and reset.');
        } else {
            console.error('.chat-box-inner element not found!');
        }

        // Close all open modals (Bootstrap 5)
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
                console.log('Closed modal:', modal.id);
            }
        });
    });

    // Add Click Event Listener to **消息**
    messagesTab.addEventListener('click', function () {
        console.log('消息 clicked!');
        window.location.reload();
    });

    // **我的好友** click handler
    $(document).on('click', '#c-my-friends', function () {
        const impersonationToken = window.chatConfig.impersonationToken;

        $.ajax({
            url: '/robot/friendlist',
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            success: function (response) {
                console.log("Response Content:", response);

                if (response.friends && response.friends.length > 0) {
                    let friendListHTML = '';
                    response.friends.forEach(friend => {
                        let avatarUrl;

                        if (friend.avatar_url) {
                            if (friend.avatar_url.startsWith('http')) {
                                // Full URL provided
                                avatarUrl = friend.avatar_url;
                            } else if (friend.avatar_url.startsWith('/')) {
                                // Absolute path provided
                                avatarUrl = `${window.location.origin}${friend.avatar_url}`;
                            } else {
                                // Relative path within storage
                                avatarUrl = `${window.location.origin}/storage/${friend.avatar_url}`.replace(/\/+/g, '/');
                            }
                        } else {
                            // Fallback to default avatar
                            avatarUrl = `${window.location.origin}/default-avatar.png`;
                        }

                        friendListHTML += `
                            <div class="friend-item d-flex align-items-center mb-3 p-2 border rounded">
                                <img src="${avatarUrl}" alt="Avatar" class="rounded-circle me-3" style="width: 50px; height: 50px;">
                                <div>
                                    <h6 class="mb-1">${friend.nickname} 【${friend.realname}】</h6>
                                </div>
                                <button class="btn btn-danger btn-sm remove-friend-button m-3" data-friendship-id="${friend.friendship_id}">
                                    移除好友
                                </button>
                            </div>`;
                    });

                    $('#friend-list-content').html(friendListHTML);
                } else {
                    $('#friend-list-content').html('<p>No friends found.</p>');
                }

                $('#friendListModal').modal('show');
            },
            error: function (xhr, status, error) {
                alert('无法获取好友名单。请再试一次。');
            }
        });
    });

    // **新的好友** click handler
    $(document).on('click', '#c-new-friends', function () {
        const impersonationToken = window.chatConfig.impersonationToken;

        $.ajax({
            url: '/robot/incoming-requests',
            type: 'GET',
            data: { impersonation_token: impersonationToken },
            success: function (response) {
                if (response.status === 'success') {
                    const requests = response.incoming_requests;
                    let contentHtml = '';

                    if (requests.length > 0) {
                        contentHtml = `
                            <div style="overflow-x: auto; max-height: 400px;">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>用户名</th>
                                            <th>姓名</th>
                                            <th>推荐码</th>
                                            <th>年龄</th>
                                            <th>日期</th>
                                            <th>余额</th>
                                            <th>冻结</th>
                                            <th>动作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        requests.forEach(request => {
                            contentHtml += `
                                <tr>
                                    <td>${request.name}</td>
                                    <td>${request.realname || 'N/A'}</td>
                                    <td>${request.referral_link}</td>
                                    <td>${request.age || 'N/A'}</td>
                                    <td>${request.created_at}</td>
                                    <td>${request.wallet_balance}</td>
                                    <td>${request.wallet_freeze}</td>
                                    <td>
                                        <button class="btn btn-success btn-sm accept-request" data-id="${request.friendship_id}">接受</button>
                                        <button class="btn btn-danger btn-sm reject-request" data-id="${request.friendship_id}">拒绝</button>
                                    </td>
                                </tr>
                            `;
                        });
                        contentHtml += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        contentHtml = '<p class="text-center">没有好友申请。</p>';
                    }

                    $('#friend-requests-content').html(contentHtml);

                    $('.accept-request').on('click', function () {
                        handleFriendRequest($(this).data('id'), 2);
                    });
                    
                    $('.reject-request').on('click', function () {
                        handleFriendRequest($(this).data('id'), 3);
                    });
                } else {
                    alert('获取好友申请失败。');
                }
            },
            error: function (xhr, status, error) {
                alert('无法获取好友申请。请再试一次。');
            }
        });

        $('#friendRequestsModal').modal('show');
    });

    function handleFriendRequest(friendshipId, status) {
        const impersonationToken = window.chatConfig.impersonationToken;
    
        $.ajax({
            url: `/robot/update-friend-status/${friendshipId}`,
            type: 'POST',
            data: { 
                impersonation_token: impersonationToken,
                status: status
            },
            headers: {
                'X-CSRF-TOKEN': window.chatConfig.csrfToken
            },
            success: function (response) {
                if (response.status === 'success') {
                    alert('操作成功！');
                    $('#friendRequestsModal').modal('hide');
                    $('#friend-requests-button').click();
                } else {
                    alert('操作失败，请重试。');
                }
            },
            error: function (xhr, status, error) {
                alert('操作失败，请重试。');
            }
        });
    }

});
