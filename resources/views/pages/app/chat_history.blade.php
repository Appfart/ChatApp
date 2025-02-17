<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        对话记录
    </x-slot>
    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/apex/apexcharts.css') }}">
        @vite(['resources/scss/light/assets/components/list-group.scss'])
        @vite(['resources/scss/light/assets/widgets/modules-widgets.scss'])
        @vite(['resources/scss/dark/assets/components/list-group.scss'])
        @vite(['resources/scss/dark/assets/widgets/modules-widgets.scss'])
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
        <style>
            .modal.edit-modal-on-top {
                z-index: 9999 !important;
            }
        </style>
    </x-slot>

    <div class="container mt-5">
        <h1>{{ $title }}</h1>
        <ul class="nav nav-tabs" id="chatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">个人聊天</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="group-tab" data-bs-toggle="tab" data-bs-target="#group" type="button" role="tab" aria-controls="group" aria-selected="false">群组聊天</button>
            </li>
        </ul>
        <div class="tab-content" id="chatTabsContent">
            <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                <div class="table-responsive mt-3">
                    <table class="table table-striped table-bordered" id="personalChatsTable">
                        <thead>
                            <tr>
                                <th>对话ID</th>
                                <th>用户</th>
                                <th>目标用户</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="group" role="tabpanel" aria-labelledby="group-tab">
                <div class="table-responsive mt-3">
                    <table class="table table-striped table-bordered w-100" id="groupChatsTable" style="width:100%;">
                        <thead>
                            <tr>
                                <th>群聊ID</th>
                                <th>群聊名称</th>
                                <th>头像</th>
                                <th>成员数量</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade edit-modal-on-top " id="editMessageModal" tabindex="-1" aria-labelledby="editMessageModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <form id="editMessageForm">
                @csrf
                <input type="hidden" name="message_id" id="edit-message-id">
                <input type="hidden" name="message_type" id="edit-message-type">
                <div class="modal-content bg-white">
                  <div class="modal-header">
                    <h5 class="modal-title" id="editMessageModalLabel">编辑消息</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                  </div>
                  <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit-message-text" class="form-label">消息内容</label>
                            <textarea class="form-control" id="edit-message-text" name="message" rows="3" required></textarea>
                        </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="submit" class="btn btn-primary">保存更改</button>
                  </div>
                </div>
            </form>
          </div>
        </div>
        
        <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl">
            <div class="modal-content bg-white">
              <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">消息详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
              </div>
              <div class="modal-body">
                <table class="table table-striped" id="detailedMessagesTable">
                    <thead>
                        <tr>
                            <th>消息ID</th>
                            <th>发送者</th>
                            <th>消息内容</th>
                            <th>图片</th>
                            <th>音频</th>
                            <th>文档</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
              </div>
            </div>
          </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/apex/apexcharts.min.js') }}"></script>
        @vite(['resources/assets/js/widgets/_wChartOne.js'])
        @vite(['resources/assets/js/widgets/_wChartTwo.js'])
        @vite(['resources/assets/js/widgets/_wActivityFour.js'])
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            let editModal;
            let detailsModal;

            function clearDetailedMessages() {
                document.querySelector('#detailedMessagesTable tbody').innerHTML = '';
            }

            function bindEditButtons() {
                const newEditButtons = document.querySelectorAll('#detailedMessagesTable .edit-message-btn');
                newEditButtons.forEach(button => {
                    button.addEventListener('click', function () {
                        const messageId = this.getAttribute('data-message-id');
                        const messageType = this.getAttribute('data-message-type');
                        let messageText = '';

                        if (messageType === 'personal') {
                            const row = document.getElementById(`personal-message-${messageId}`);
                            if(row) {
                                messageText = row.querySelector('.message-text').innerText;
                            }
                        } else {
                            const row = document.getElementById(`group-message-${messageId}`);
                            if(row) {
                                messageText = row.querySelector('.message-text').innerText;
                            }
                        }

                        document.getElementById('edit-message-id').value = messageId;
                        document.getElementById('edit-message-type').value = messageType;
                        document.getElementById('edit-message-text').value = messageText;

                        editModal.show();
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editMessageModal'));
                detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

                $('#personalChatsTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "ajax": "{{ route('chathistory.personalData') }}",
                    "columns": [
                        { "data": "id" },
                        { "data": "user.name" },
                        { "data": "targetUser.name" },
                        {
                            "data": "created_at",
                            "render": function(data) {
                                var date = new Date(data);
                                var year = date.getFullYear();
                                var month = String(date.getMonth() + 1).padStart(2, '0');
                                var day = String(date.getDate()).padStart(2, '0');
                                var hours = String(date.getHours()).padStart(2, '0');
                                var minutes = String(date.getMinutes()).padStart(2, '0');
                                var seconds = String(date.getSeconds()).padStart(2, '0');
                                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                            }
                        },
                        { "data": "actions", "orderable": false, "searchable": false },
                    ],
                    "order": [[ 3, "desc" ]],
                    "pageLength": 10,
                    "responsive": true,
                });
        
                $('#groupChatsTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "ajax": "{{ route('chathistory.groupData') }}",
                    "columns": [
                        { "data": "id" },
                        { "data": "chatname" },
                        { "data": "avatar", "orderable": false, "searchable": false },
                        { "data": "members_count" },
                        {
                            "data": "created_at",
                            "render": function(data) {
                                var date = new Date(data);
                                var year = date.getFullYear();
                                var month = String(date.getMonth() + 1).padStart(2, '0');
                                var day = String(date.getDate()).padStart(2, '0');
                                var hours = String(date.getHours()).padStart(2, '0');
                                var minutes = String(date.getMinutes()).padStart(2, '0');
                                var seconds = String(date.getSeconds()).padStart(2, '0');
                                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                            }
                        },
                        { "data": "actions", "orderable": false, "searchable": false },
                    ],
                    "order": [[ 4, "desc" ]],
                    "pageLength": 10,
                    "responsive": true,
                });

                $(document).on('click', '.view-details-btn', function() {
                    const conversationId = $(this).data('conversation-id');
                    const type = $(this).data('type');
                    clearDetailedMessages();
                    const modalTitle = document.getElementById('detailsModalLabel');
                    if(type === 'personal') {
                        modalTitle.textContent = `个人对话 #${conversationId} 的消息详情`;
                    } else {
                        modalTitle.textContent = `群组对话 #${conversationId} 的消息详情`;
                    }

                    let url = '';
                    if(type === 'personal') {
                        url = `/app/chathistory/personal/${conversationId}/messages`;
                    } else {
                        url = `/app/chathistory/group/${conversationId}/messages`;
                    }

                    fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                    })
                    .then(response => response.json())
                    .then(data => {
                        data.messages.forEach(message => {
                            const row = document.createElement('tr');
                            row.id = `${type === 'personal' ? 'personal' : 'group'}-message-${message.id}`;
                            row.innerHTML = `
                                <td>${message.id}</td>
                                <td>${message.sender}</td>
                                <td class="message-text">${message.content}</td>
                                <td>${message.image_url ? `<a href="${message.image_url}" target="_blank">查看图片</a>` : '无'}</td>
                                <td>${message.audio_url ? `<a href="${message.audio_url}" target="_blank">播放音频</a>` : '无'}</td>
                                <td>${message.doc_url ? `<a href="${message.doc_url}" target="_blank">查看文档</a>` : '无'}</td>
                                <td>${message.created_at}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-message-btn" data-message-id="${message.id}" data-message-type="${type}">编辑</button>
                                </td>
                            `;
                            document.querySelector('#detailedMessagesTable tbody').appendChild(row);
                        });
                        detailsModal.show();
                        bindEditButtons();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('加载消息详情时发生错误。');
                    });
                });

                const editForm = document.getElementById('editMessageForm');
                editForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const messageId = document.getElementById('edit-message-id').value;
                    const messageType = document.getElementById('edit-message-type').value;
                    const messageText = document.getElementById('edit-message-text').value;

                    fetch('{{ route("chat.updateMessage") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            message_id: messageId,
                            message_type: messageType,
                            message: messageText,
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (messageType === 'personal') {
                                const row = document.getElementById(`personal-message-${messageId}`);
                                if (row) row.querySelector('.message-text').innerText = messageText;
                            } else {
                                const row = document.getElementById(`group-message-${messageId}`);
                                if (row) row.querySelector('.message-text').innerText = messageText;
                            }
                            const detailedRow = document.getElementById(`${messageType === 'personal' ? 'personal' : 'group'}-message-${messageId}`);
                            if(detailedRow){
                                detailedRow.querySelector('.message-text').innerText = messageText;
                            }
                            editModal.hide();
                            alert('消息更新成功。');
                        } else {
                            alert(data.error || '消息更新失败。');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('更新消息时发生错误。');
                    });
                });
            });
        </script>
    </x-slot:footerFiles>
</x-base-layout>