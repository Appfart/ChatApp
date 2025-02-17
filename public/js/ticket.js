$(document).ready(function () {

    if (typeof Echo === 'undefined' || typeof Pusher === 'undefined') {
        console.error('Echo or Pusher not found. Ensure that the CDN scripts are correctly included.');
        return;
    }

    const appKey = window.PUSHER_APP_KEY;
    const appCluster = window.PUSHER_APP_CLUSTER;

    if (!appKey || !appCluster) {
        console.error('Pusher configuration missing. Ensure PUSHER_APP_KEY and PUSHER_APP_CLUSTER are set.');
        return;
    }

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: appKey,
        cluster: appCluster,
        forceTLS: true,
        encrypted: true,
    });

    // Debugging Echo connection
    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('Echo connected to Pusher');
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        console.warn('Echo disconnected from Pusher');
    });

    window.Echo.connector.pusher.connection.bind('error', (err) => {
        console.error('Pusher connection error:', err);
    });

    // Determine if currentTicketId is defined (indicates show.blade.php)
    if (typeof currentTicketId !== 'undefined') {
        const ticketChannel = `ticket.${currentTicketId}`;

        window.Echo.channel(ticketChannel)
            .listen('.NewTicketMessage', (data) => {
                console.log("ticket msg", data); // Debugging log

                // Initialize messageContent as an empty string
                let messageContent = '';

                // Conditionally add the message paragraph if it exists
                if (data.message.message) {
                    messageContent += `<p>${escapeHtml(data.message.message)}</p>`;
                }

                // If there's an image, add it
                if (data.message.image_url) {
                    messageContent += `
                        <div class="message-image my-2">
                            <img src="${escapeHtml(data.message.image_url)}" alt="Message Image" class="img-fluid rounded">
                        </div>
                    `;
                }

                const messageContainer = `
                    <div class="message-wrapper ${data.message.user.realname === currentUserRealName ? 'message-right' : 'message-left'}">
                        <div class="card">
                            <div class="card-header">
                                <strong>${escapeHtml(data.message.user.realname)}</strong> - ${moment(data.message.created_at).format('DD MMM YYYY, HH:mm')}
                            </div>
                            <div class="card-body">
                                ${messageContent}
                            </div>
                        </div>
                    </div>
                `;
                if ($('#messages').length) {
                    $('#messages').append(messageContainer);
                    $('#messages').scrollTop($('#messages')[0].scrollHeight);
                }
            })
            .listen('TicketClosed', (data) => {
                console.log("ticket close", data); // Debugging log

                const ticketId = data.ticket.id;
                const ticketRow = $(`#ticket-row-${ticketId}`);

                if (ticketRow.length) {
                    ticketRow.remove();
                } else {
                    console.warn(`Ticket row with ID ${ticketId} not found.`);
                }
            });
    } else {
        window.Echo.channel('tickets')
            .listen('.NewTicketMessage', (data) => {
                console.log("receving ticket msg", data); // Debugging log

                // Sanitize and prepare message content
                let messageContent = '';
                
                if (data.message.message) {
                    messageContent += `<p>${escapeHtml(data.message.message)}</p>`;
                }
            
                // If there's an image, add it
                if (data.message.image_url) {
                    messageContent += `
                        <div class="message-image my-2">
                            <img src="${escapeHtml(data.message.image_url)}" alt="Message Image" class="img-fluid rounded">
                        </div>
                    `;
                }
            
                const messageContainer = `
                    <div class="message-wrapper ${data.message.user.realname === currentUserRealName ? 'message-right' : 'message-left'}">
                        <div class="card">
                            <div class="card-header">
                                <strong>${escapeHtml(data.message.user.realname)}</strong> - ${moment(data.message.created_at).format('DD MMM YYYY, HH:mm')}
                            </div>
                            <div class="card-body">
                                ${messageContent}
                            </div>
                        </div>
                    </div>
                `;
                if ($('#messages').length) {
                    $('#messages').append(messageContainer);
                    $('#messages').scrollTop($('#messages')[0].scrollHeight);
                }
                
                // Extract the ticket ID from the received data
                const ticketId = data.message.ticket_id;
            
                // Select the corresponding table row using the ticket ID
                const $ticketRow = $('#ticket-row-' + ticketId);
            
                if ($ticketRow.length) {
                    // Move the ticket row to the top of the table's tbody
                    $ticketRow.prependTo('#tickets-table tbody');
            
                    // Update the 'updated_at' data attribute
                    const newUpdatedAtISO = moment(data.message.created_at).toISOString();
                    $ticketRow.attr('data-updated-at', newUpdatedAtISO);
            
                    // Update the "最后更新" column (6th <td>, index 5)
                    const newUpdatedAtText = moment(data.message.created_at).fromNow();
                    $ticketRow.find('td').eq(5).text(newUpdatedAtText);
                    
                    const latestMessage = escapeHtml(data.message.message);
                    $ticketRow.find('td').eq(4).text(latestMessage);
            
                    // Optionally, update the status if it's part of the received data
                    if (data.ticket_status) {
                        const statusClass = 'status-' + data.ticket_status.toLowerCase();
                        $ticketRow.find('.ticket-status')
                                 .removeClass(function(index, className) {
                                     return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
                                 })
                                 .addClass(statusClass)
                                 .text(data.ticket_status.charAt(0).toUpperCase() + data.ticket_status.slice(1));
                    }
                } else {
                    console.warn(`Ticket row with ID ${ticketId} not found.`);
                }
            })
        
            .listen('NewTicketCreated', (data) => {
                console.log("ticket create", data); // Debugging log
                const tableBody = $('table tbody');

                // Determine if the current user can see the close button
                const canCloseTicket = window.USER_ROLE !== 'user' && data.ticket.status !== 'closed';

                // Construct the close button form if the user has permissions
                const closeTicketForm = canCloseTicket ? `
                    <form action="/tickets/${escapeHtml(data.ticket.id)}/close" method="POST" class="close-ticket-form" style="display:inline;">
                        <input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
                        <button type="submit" class="btn btn-warning btn-sm">关闭</button>
                    </form>
                ` : '';

                const newRow = `
                    <tr id="ticket-row-${escapeHtml(data.ticket.id)}" 
                        data-ticket-id="${escapeHtml(data.ticket.id)}" 
                        data-updated-at="${escapeHtml(data.ticket.updated_at)}">
                        <td>${escapeHtml(data.ticket.id)}</td>
                        <td>${escapeHtml(data.ticket.subject)}</td>
                        <td class="ticket-status status-${escapeHtml(data.ticket.status.toLowerCase())}">
                            ${capitalizeFirstLetter(data.ticket.status)}
                        </td>
                        <td>${escapeHtml(data.ticket.user.realname)}</td>
                        <td class="latest-message">
                            ${escapeHtml(data.ticket.message ? data.ticket.message : '暂无消息。')}
                        </td>
                        <td>${moment(data.ticket.updated_at).fromNow()}</td>
                        <td class="message-sender">
                            ${escapeHtml(data.ticket.user.realname)}
                        </td>
                        <td>
                            <a href="/tickets/${escapeHtml(data.ticket.id)}" class="btn btn-info btn-sm">查看</a>
                            ${closeTicketForm}
                        </td>
                    </tr>
                `;
                tableBody.append(newRow);
                sortTicketsTable(data.ticket.id);
                updatePendingTicketCount(1);
            })

            .listen('TicketClosed', (data) => {
                console.log("ticket close", data); // Debugging log
                const ticketId = data.ticket.id;
                const ticketRow = $(`#ticket-row-${ticketId}`);

                if (ticketRow.length) {
                    ticketRow.remove();
                } else {
                    console.warn(`Ticket row with ID ${ticketId} not found.`);
                }
                updatePendingTicketCount(-1);
            });
    }

    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        //console.log('Pusher connection state changed:', states);
    });

    window.Echo.connector.pusher.connection.bind('error', (err) => {
        console.error('Pusher connection error:', err);
    });

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Close Ticket Functionality
    $(document).on('submit', '.close-ticket-form', function (e) {
        e.preventDefault();
    
        const form = $(this);
        const ticketId = form.closest('tr').data('ticket-id');
        const csrfToken = form.find('input[name="_token"]').val(); // Retrieve the CSRF token from the form
    
        if (!confirm('您确定要关闭此票据吗？')) {
            return;
        }
    
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: {
                _token: csrfToken, // Include the CSRF token in the data
            },
            success: function (response) {
                $(`#ticket-row-${ticketId}`).remove();
                alert('票据已成功关闭。');
            },
            error: function (xhr) {
                console.error('关闭票据时发生错误:', xhr);
                alert('关闭票据时发生错误。');
            },
        });
    });

    // Log existing table rows (for debugging)
    $('#tickets-table tbody tr').each(function () {
        //console.log("Row ID:", $(this).attr('id'), "Updated At:", $(this).data('updated-at'));
    });

});

// 更新待处理票据数量的函数
function updatePendingTicketCount(change) {
    const countElement = $('#pending-ticket-count');
    if (countElement.length) {
        let currentCount = parseInt(countElement.text()) || 0;
        currentCount += change;
        currentCount = currentCount < 0 ? 0 : currentCount; // 防止计数为负值
        countElement.text(currentCount);
    } else {
        console.warn('未找到待处理票据计数元素。');
    }
}

// 将字符串的首字母大写的函数
function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// 对票据表进行排序的函数
function sortTicketsTable(ticketId) {
    console.log("正在运行 sortTicketsTable...");

    const tableBody = $('#tickets-table tbody');
    const ticketRow = $(`#ticket-row-${ticketId}`);

    if (ticketRow.length) {
        // 删除现有行以便将其重新插入到表格顶部
        ticketRow.detach();
        console.log(`正在更新 ticket-row-${ticketId}`);
    }

    // 将更新的行添加到表格顶部
    tableBody.prepend(ticketRow);

    console.log(`已更新 ${ticketId} 的 data-updated-at: ${ticketRow.data('updated-at')}`);
    console.log(`ticket-row-${ticketId} 已移动到顶部。`);
}

function escapeHtml(text) {
    if (typeof text !== 'string') {
        console.warn('Invalid input to escapeHtml:', text);
        text = String(text); // Convert to string
    }
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// 处理添加回复表单提交
$(document).on('submit', '#add-reply-form', function (e) {
    e.preventDefault();

    const form = $(this)[0];
    const formData = new FormData(form);

    // Remove 'message' field if it's empty
    if (!formData.get('message')) {
        formData.delete('message');
    }

    $.ajax({
        url: form.action,
        type: 'POST',
        data: formData,
        processData: false, // 对于文件上传非常重要
        contentType: false, // 对于文件上传非常重要
        success: function (response) {
            // 清空文本区域和文件输入
            $('textarea[name="message"]').val('');
            $('#image').val('');

            // 可选：显示成功消息
            alert('消息已成功发送。');

            // 不要在这里追加消息；Echo 会处理它
        },
        error: function (xhr) {
            console.error('发送消息时出错:', xhr);
            alert('发送消息时发生错误。');
        },
    });
});

