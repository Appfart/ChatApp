<x-base-layout :scrollspy="false">
    
    <x-slot:pageTitle>
        【{{ $user->realname }}】
    </x-slot>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/apps/chat.scss'])
        @vite(['resources/scss/dark/assets/apps/chat.scss'])
        <link rel="stylesheet" href="{{ asset('css/chat.css') }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href=" https://cdn.jsdelivr.net/npm/lightgallery@2.8.2/css/lightgallery.min.css " rel="stylesheet">
        
        <script>
            window.chatConfig = {
                userId: {{ json_encode($user->id) }},
                impersonationToken: "{{ request()->get('impersonation_token') }}",
                csrfToken: "{{ csrf_token() }}",
            };
        </script>
    </x-slot:headerFiles>
    
    <div class="chat-section layout-top-spacing">
        <div class="row">
            <div class="col-xl-12 col-lg-12 col-md-12">
                <div class="chat-system">
                    
                    <div class="hamburger"></div>
                    
                    <div class="side-menu">
                        <div class="menu-item">
                            <div class="avatar-default"></div>
                        </div>
                        <div class="menu-sub-item">
                            <div class="sub-item" id="messages-tab">
                                <img src="/img/chat.png" alt="Chat">
                                <span>消息</span>
                            </div>
                            <div class="sub-item" id="contacts-tab">
                                <img src="/img/contact.png" alt="Contact">
                                <span>通讯录</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-list-box">
                        <div class="search">
                            <input type="text" class="form-control" placeholder="搜索" />
                            <div class="dropdown">
                            <button id="more" class="more-button dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            </button>
                            <ul class="dropdown-menu">
                                <li><button id="search-add-friend-button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addFriendModal">添加好友</button></li>
                                <li><button id="group-chat-button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#createGroupModal">创建群聊</button></li>
                                <li><button id="create-category" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#createCategoryModal">创建分组</button></li>
                                <li><button id="my-qr-code" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#myQRCodeModal">我的二维码</button></li>
                            </ul>
                        </div>
                        </div>
                        <hr>
                        
                        <!--<div class="d-flex justify-content-start gap-2 m-2">
                            <button id="friend-list-button" class="btn btn-dark flex-fill" data-impersonation-token="{{ request()->get('impersonation_token') }}">
                                我的好友
                            </button>
                            <button id="friend-requests-button" class="btn btn-primary flex-fill">
                                好友申请
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-start gap-2 m-2">
                            <button id="group-chat-button" class="btn btn-info flex-fill">
                                拉群聊天
                            </button>
                            <button id="search-add-friend-button" class="btn btn-success flex-fill">
                                搜索好友
                            </button>
                        </div>-->

                        <div class="people">
                            @foreach($sortedChats as $chat)
                                @if($chat['type'] === 'conversation')
                                    @php
                                        $conversation = $chat['data'];
                                        $targetUser = $conversation->name == $user->id ? $conversation->targetUser : $conversation->user;
                                        $latestMessage = $chat['latest_message'];
                                        $unreadCount = $chat['unread_count'];
                                        $nickname = $chat['nickname'] !== 'none' ? $chat['nickname'] : $targetUser->realname;
                                    @endphp
                                    @if($targetUser)
                                        <div class="person" data-chat-type="conversation" data-target-id="{{ $targetUser->id }}" data-chat-id="{{ $conversation->id }}">
                                            <div class="user-info">
                                                <div class="f-head">
                                                    @if($targetUser->avatar)
                                                        <img src="{{ asset('storage/' . $targetUser->avatar) }}" alt="Avatar" class="avatar">
                                                    @else
                                                        <div class="avatar-default"></div>
                                                    @endif
                                                </div>
                                                <div class="f-body">
                                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                        <div class="meta-info" style="flex: 1;">
                                                            <span class="user-name" data-user='{"id":{{ $targetUser->id }}, "nickname": "{{ $nickname }}", "realname": "{{ $targetUser->realname }}"}'>{{ $nickname }}</span>
                                                            @if($unreadCount > 0)
                                                                <span class="unread-count badge bg-danger">{{ $unreadCount }}</span>
                                                            @else
                                                                <span class="unread-count badge bg-danger" style="display:none;"></span>
                                                            @endif
                                                        </div>
                                                        
                                                        <div class="user-meta-time" style="white-space: nowrap; margin-left: 10px;">
                                                            {{ $latestMessage ? \Carbon\Carbon::parse($latestMessage->created_at)->format('m/d h:i A') : '暂无消息' }}
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="preview" style="margin-top: 5px;">
                                                        @if ($latestMessage)
                                                            @if ($latestMessage->message)
                                                                {{ $latestMessage->message }}
                                                            @elseif ($latestMessage->image_url || $latestMessage->audio_url || $latestMessage->doc_url)
                                                                有新消息
                                                            @else
                                                                暂没信息
                                                            @endif
                                                        @else
                                                            暂没信息
                                                        @endif
                                                    </div>
                                                    
                                                    <!--<div>
                                                        <span class="last-login badge badge-success">
                                                            最后活跃: 
                                                            @if($user->last_login)
                                                                {{ \Carbon\Carbon::parse($user->last_login)->diffForHumans() }}
                                                            @else
                                                                未上线
                                                            @endif
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="action-icons d-flex align-items-center gap-2 text-dark">
                                                        <a href="javascript:void(0)" class="remove-conversation" data-chat-id="{{ $conversation->id }}" data-is-group-chat="0" title="移除对话"> 
                                                            <i class="fas fa-trash-alt"></i> 
                                                        </a>
                                                    </div>-->
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @elseif($chat['type'] === 'grpchat')
                                    @php
                                        $grpchat = $chat['data'];
                                        $latestMessage = $chat['latest_message'];
                                        $unreadCount = $chat['unread_count'];
                                    @endphp
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
                                            <div class="f-body">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <div class="meta-info" style="flex: 1;">
                                                        <span class="user-name">{{ $grpchat->chatname }}</span>
                                                        @if($unreadCount > 0)
                                                            <span class="unread-count badge bg-danger">{{ $unreadCount }}</span>
                                                        @else
                                                            <span class="unread-count badge bg-danger" style="display: none;"></span>
                                                        @endif
                                                    </div>
                                                    <div class="user-meta-time" style="white-space: nowrap; margin-left: 10px;">
                                                        {{ $latestMessage ? \Carbon\Carbon::parse($latestMessage->created_at)->format('m/d h:i A') : '暂无消息' }}
                                                    </div>
                                                </div>
                                            
                                                <div class="preview" style="margin-top: 5px;">
                                                    @if ($latestMessage)
                                                        @if ($latestMessage->message)
                                                            {{ $latestMessage->message }}
                                                        @elseif ($latestMessage->image_url || $latestMessage->audio_url || $latestMessage->doc_url)
                                                            有新消息
                                                        @else
                                                            暂没信息
                                                        @endif
                                                    @else
                                                        暂没信息
                                                    @endif
                                                </div>
                                                
                                                <!-- <div class="action-icons d-flex align-items-center gap-2 text-dark" style="margin-top: 10px;">
                                                    @if(in_array($user->id, $grpchat->admins))
                                                        <a href="javascript:void(0)" class="open-settings" data-grpchat-id="{{ $grpchat->id }}" title="群组设置">
                                                            <i class="fas fa-cogs"></i>
                                                        </a>
                                                        <a href="javascript:void(0)" class="open-mute-settings" data-grpchat-id="{{ $grpchat->id }}" title="禁言成员">
                                                            <i class="fas fa-volume-mute"></i>
                                                        </a>
                                                    @endif
                                                    
                                                    <a href="javascript:void(0)" class="open-member-list" data-grpchat-id="{{ $grpchat->id }}" title="查看成员">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                    
                                                    <a href="javascript:void(0)" class="invite-members" data-grpchat-id="{{ $grpchat->id }}" title="邀请成员">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                    
                                                    @if($user->id !== $grpchat->owner)
                                                        <a href="javascript:void(0)" class="quit-group" data-grpchat-id="{{ $grpchat->id }}" title="退出群组">
                                                            <i class="fas fa-sign-out-alt"></i>
                                                        </a>
                                                    @endif
                                                </div> -->
                                            </div>

                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    
                    <div class="chat-box" style="background-image:none">
                        <div class="chat-box-inner" style="min-height:90%; position: relative;">
                            <div class="chat-not-selected">
                                <p>选择对话</p>
                            </div>
                            <div class="chat-box-header" style="display: none;">
                                <div class="left-group">
                                    <!-- Dynamic Content -->
                                </div>

                                <div class="action-buttons">
                                    <button id="history-button" class="btn-icon">
                                        <i class="far fa-clock"></i>
                                    </button>
                                    <button id="settings-button" class="btn-icon">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>

                            </div>
                            
                            <div class="chat-meta-user">
                                <div class="current-chat-user-name">
                                    <span class="name"></span>
                                </div>
                            </div>
                            
                            <div class="chat-conversation-box" style="flex: 1; overflow-y: auto; max-height: calc(100vh - 200px); padding-left: 20px; padding-right: 20px; padding-top:10px">
                                <div class="chat-conversation-box-scroll">
                                    
                                    
                                </div>
                            </div>
                            
                            <!-- Preview Container -->
                            <div id="image-preview-container" class="image-preview-container" style="display: none;">
                                <div class="preview-header">
                                    <span>照片预览</span>
                                    <button type="button" class="close-preview">&times;</button>
                                </div>
                                <div class="preview-body">
                                    <img id="image-preview" src="#" alt="Image Preview">
                                </div>
                                <div class="preview-footer">
                                    <button type="button" id="send-preview-btn" class="btn btn-primary btn-sm">Send</button>
                                    <button type="button" id="cancel-preview-btn" class="btn btn-secondary btn-sm">Cancel</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Updated Chat Footer with Textarea -->
                        <div class="chat-footer">
                            <div class="announcement-marquee" id="announcement-marquee">
                                <span>暂无公告</span>
                            </div>
                            <div id="reply-context-container" style="display: none; background-color: #f1f1f1; padding: 5px; border-left: 3px solid #007bff; margin-bottom: 5px;">
                                <span id="replying-to">回复: <strong></strong></span>
                                <button type="button" id="cancel-reply" class="btn btn-sm btn-link">取消</button>
                            </div>
                            <div class="chat-input">
                                <form id="chat-form" class="chat-form" action="javascript:void(0);">
                                    <div class="input-group footer-input-group">
                                        
                                        <div class="additional-icons">
                                            <button type="button" class="btn btn-link icon-button" id="audio-button" title="Send Audio">
                                                <i class="fas fa-microphone"></i>
                                            </button>
                                            <button type="button" class="btn btn-link icon-button" id="image-button" title="Send Image">
                                                <i class="fas fa-image"></i>
                                            </button>
                                            <button type="button" class="btn btn-link icon-button" id="video-button" title="Send Video">
                                                <i class="fas fa-video"></i>
                                            </button>
                                            <button type="button" class="btn btn-link icon-button" id="document-button" title="Send Document">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-link icon-button" id="emoji-button" title="Send Emoji">
                                                <i class="fas fa-smile"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="" style="width:100%; display:flex; gap: 5px;">
                                            <textarea id="message-input" class="form-control message-input" placeholder="请输入消息" style="resize: auto;"></textarea>
                                            <input type="hidden" id="reply-to-id" value="">
                                        </div>
                                        
                                        <button type="submit" class="btn" id="send-button">
                                            发送
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!-- Emoji Picker Container -->
                            <div id="emoji-picker-container" style="position: absolute; bottom: 60px; right: 20px; z-index: 1000; display: none;"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
     </div>
     
    <div class="modal fade" id="removeConversationModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">确认移除对话</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>您确定要移除此对话吗？此操作无法撤销。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger confirm-remove">确认</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                </div>
            </div>
        </div>
    </div>
     
    <div class="modal fade" id="viewMembersModal" tabindex="-1" role="dialog" aria-labelledby="viewMembersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewMembersModalLabel">群组成员</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="关闭">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <input type="hidden" name="grpchat_id" id="grpchat-id">
                </div>
                <div class="modal-body">
                    <!-- Search Input -->
                    <div class="mb-3">
                        <label for="view-member-search" class="form-label"><strong>搜索成员</strong></label>
                        <input type="text" class="form-control" id="view-member-search" placeholder="输入昵称或真实姓名进行搜索">
                    </div>
                    <div id="member-list-content">
                        <p class="text-center">加载中...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="searchAddFriendModal" tabindex="-1" role="dialog" aria-labelledby="searchAddFriendModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchAddFriendModalLabel">搜索并添加好友</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="关闭">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="search-friend-form">
                        <div class="form-group">
                            <label for="search-query">搜索好友</label>
                            <input type="text" class="form-control" id="search-query" name="query" placeholder="输入用户ID" required>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">搜索</button>
                    </form>
                    <hr>
                    <div id="search-results">
                        <!-- Search results will appear here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="friendListModal" tabindex="-1" role="dialog" aria-labelledby="friendListModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="friendListModalLabel">好友名单</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="friend-list-content">
                        <p class="text-center">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="friendRequestsModal" tabindex="-1" role="dialog" aria-labelledby="friendRequestsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="friendRequestsModalLabel">好友申请</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="friend-requests-content">
                        <p class="text-center">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="groupChatModal" tabindex="-1" role="dialog" aria-labelledby="groupChatModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupChatModalLabel">拉群聊天</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="group-chat-step-1">
                        <h5>第一步: 设置群组名称</h5>
                        <input type="text" id="group-chat-name" class="form-control" placeholder="输入群组名称" required />
                    </div>
                    <div id="group-chat-step-2" style="display: none;">
                        <h5>第二步: 选择好友</h5>
                        <div id="friend-selection" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-center">加载好友名单中...</p>
                        </div>
                    </div>
                    <div id="group-chat-step-3" style="display: none;">
                        <h5>第三步: 确认群组</h5>
                        <p>群组名称: <span id="confirm-group-name"></span></p>
                        <p>群成员:</p>
                        <ul id="confirm-group-members"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="prev-step" class="btn btn-secondary" style="display: none;">返回</button>
                    <button type="button" id="next-step" class="btn btn-primary">下一步</button>
                    <button type="button" id="create-group-chat" class="btn btn-success" style="display: none;">创建群组</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="groupSettingsModal" tabindex="-1" aria-labelledby="groupSettingsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content ">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white" id="groupSettingsLabel"><i class="fas fa-cogs"></i> 群组设置</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
    
                <div class="modal-body">
                    <form id="group-settings-form" enctype="multipart/form-data">
                        <input type="hidden" name="grpchat_id" id="group-settings-grpchat-id">
    
                        <div class="mb-3">
                            <label for="group-name" class="form-label"><strong>群组名称</strong></label>
                            <input type="text" class="form-control" id="group-name" name="chatname" placeholder="输入群组名称">
                        </div>
    
                        <div class="mb-3">
                            <label for="group-avatar" class="form-label"><strong>群组头像</strong></label>
                            <input type="file" class="form-control" id="group-avatar" name="avatar">
                            <small class="text-muted">上传图片作为群组头像</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="group-announcement" class="form-label"><strong>群组公告</strong></label>
                            <textarea class="form-control" id="group-announcement" name="announcement" rows="3" placeholder="输入群组公告"></textarea>
                            <small class="text-muted">群组公告会显示给所有群组成员</small>
                        </div>
    
                        <!-- 移除群成员 Section with Search -->
                        <div class="mb-3">
                            <label class="form-label"><strong>移除群成员</strong></label>
                            <input type="text" class="form-control mb-2" id="remove-member-search" placeholder="搜索成员">
                            <ul id="member-list" class="list-group border">
                                <!-- Members will be populated dynamically -->
                            </ul>
                        </div>
    
                        <div class="mb-3">
                            <label for="available-members" class="form-label"><strong>添加成员</strong></label>
                            <div class="input-group">
                                <select id="available-members" class="form-select" aria-label="选择成员">
                                </select>
                                <button id="add-member-btn" type="button" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> 添加
                                </button>
                            </div>
                            <small class="text-muted">从好友列表中选择成员加入群组</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>群组设置</strong></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="add-friend-toggle" name="add_friend">
                                        <label class="form-check-label" for="add-friend-toggle">允许添加好友</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="hide-members-toggle" name="hide_members">
                                        <label class="form-check-label" for="hide-members-toggle">隐藏群成员</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow-invite-toggle" name="allow_invite">
                                        <label class="form-check-label" for="allow-invite-toggle">允许邀请成员</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="block-quit-toggle" name="block_quit">
                                        <label class="form-check-label" for="block-quit-toggle">禁止退出群聊</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="mute-chat-toggle" name="mute_chat">
                                        <label class="form-check-label" for="mute-chat-toggle">禁言群聊</label>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                    </form>
                    
                    <!-- Admin Management Section with Search -->
                    <div id="admin-management" style="display: none;">
                        <h5>管理员管理</h5>
                        <div class="mb-3">
                            <label for="current-admins" class="form-label"><strong>当前管理员</strong></label>
                            <input type="text" class="form-control mb-2" id="admin-search" placeholder="搜索管理员">
                            <ul id="current-admins" class="list-group mb-3">
                                <!-- Example List Item for Current Admin -->
                                <li class="list-group-item d-flex justify-content-between align-items-center admin-item">
                                    <span>管理员名称</span>
                                    <!-- Remove Admin Button -->
                                    <button class="btn btn-danger btn-sm remove-admin" data-id="5" data-grpchat-id="12">移除管理员</button>
                                </li>
                                <!-- More admins... -->
                            </ul>
                        </div>
                        <div class="mb-3">
                            <label for="add-admin-select" class="form-label"><strong>添加管理员</strong></label>
                            <select id="add-admin-select" class="form-select">
                                <option value="">选择用户</option>
                                <!-- Populated via JS -->
                            </select>
                            <button type="button" class="btn btn-success mt-2" id="add-admin-btn">添加为管理员</button>
                        </div>
                        <input type="hidden" name="grpchat_id" id="group-settings-grpchat-id">
                    </div>
                </div>
    
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" id="save-group-settings" class="btn btn-success">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="audioRecordingModal" tabindex="-1" role="dialog" aria-labelledby="audioRecordingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="audioRecordingModalLabel">录制音频</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body text-center">
                    <button id="start-record-btn" class="btn btn-danger btn-lg mb-3">
                        <i class="fas fa-microphone"></i> 开始录音
                    </button>
                    <br>
                    <button id="stop-record-btn" class="btn btn-secondary btn-lg mb-3" disabled>
                        <i class="fas fa-stop"></i> 发送录音
                    </button>
                    <br>
                    <button id="cancel-record-btn" class="btn btn-warning btn-lg" disabled>
                        <i class="fas fa-times-circle"></i> 取消录音
                    </button>
                    <div id="recording-indicator" class="mt-3" style="display: none;">
                        <span class="badge bg-danger">正在录音...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="muteSettingsModal" tabindex="-1" aria-labelledby="muteSettingsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content ">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white" id="muteSettingsLabel">
                        <i class="fas fa-volume-mute"></i> 管理禁言成员
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="mute-settings-form">
                        <input type="hidden" name="mute_grpchat_id" id="grpchat-id-mute">
    
                        <!-- Search Input -->
                        <div class="mb-3">
                            <label for="member-search" class="form-label"><strong>搜索成员</strong></label>
                            <input type="text" class="form-control" id="member-search" placeholder="输入昵称或真实姓名进行搜索">
                        </div>
    
                        <!-- Member List -->
                        <div class="mb-3">
                            <label class="form-label"><strong>选择需要禁言的成员</strong></label>
                            <ul id="mute-member-list" class="list-group">
                                <!-- Members will be populated dynamically -->
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" id="save-mute-settings" class="btn btn-success">
                        <i class="fas fa-save"></i> 保存禁言设置
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inviteMembersModal" tabindex="-1" role="dialog" aria-labelledby="inviteMembersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content ">
                <div class="modal-header">
                    <h5 class="modal-title" id="inviteMembersModalLabel">邀请好友到群组</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="关闭">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Search Input -->
                    <div class="mb-3">
                        <label for="invite-member-search" class="form-label"><strong>搜索好友</strong></label>
                        <input type="text" class="form-control" id="invite-member-search" placeholder="输入昵称或真实姓名进行搜索">
                    </div>
    
                    <div id="invite-friend-list-content">
                        <p class="text-center">加载中...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="miniPopout" class="mini-popout d-none">
        <div class="mini-popout-content">
            <div class="popout-header text-center bg-dark text-light">
                <img id="popout-avatar" class="avatar mx-auto" src="" alt="User Avatar">
                <p id="popout-name" class="name mt-2"></p>
            </div>
            <div class="popout-info text-left">
                <p><strong>备注:</strong> <span id="popout-nickname" class="nickname"></span></p>
                <p><strong>年龄:</strong> <span id="popout-age" class="age"></span></p>
                <p><strong>当前在线:</strong> <span>是</span></p>
            </div>
            <div class="popout-input">
                <p><strong>备注:</strong> 
                <input type="text" id="popout-input" placeholder="放个备注吧..." class="form-control"></p>
            </div>
            <div class="popout-buttons text-center mt-3">
                <button id="popout-save" class="btn btn-primary">保存</button>
                <button id="popout-close" class="btn btn-secondary">取消</button>
            </div>
            <button id="popout-send-msg" class="btn btn-success d-none">发送消息</button>
        </div>
    </div>

    <div id="conversation-settings-overlay" class="overlay-menu d-none">
        <div class="overlay-header">
            <span>个人聊天设置</span>
            <button type="button" class="close-overlay">&times;</button>
        </div>
        <div class="overlay-body">
            <ul class="settings-list" style="list-style: none; padding: 0;">
                @if(isset($targetUser))
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn" id="remark-btn" 
                        data-user='@json(["id" => $targetUser->id ?? 0, "nickname" => $nickname ?? "", "name" => $targetUser->realname ?? "未知"])'>
                        备注
                    </button>
                </li>
                <hr>
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn" id="category-btn" data-user='@json(["id" => $targetUser->id, "nickname" => $nickname, "name" => $targetUser->realname])'>分组</button>
                </li>
                
                <hr>
                @endif
                <!--<li class="settings-item">
                    <div class="toggle-label" style="display: flex; align-items: center; justify-content: space-between;">
                        置顶聊天
                        <label class="toggle-slider">
                            <input type="checkbox" id="pin-chat-toggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                </li>-->
                <hr>
            </ul>
            @if(isset($conversation))
                <button type="button" class="btn btn-danger btndelete remove-conversation" id="delete-friend-btn" data-chat-id="{{ $conversation->id }}"> 删除好友 </button>
            @endif
        </div>
    </div>
    
    @isset($grpchat)
    <div id="grpchat-settings-overlay" class="overlay-menu d-none">
        <div class="overlay-header">
            <span>群主聊天设置</span>
            <button type="button" class="close-overlay">&times;</button>
        </div>
        <div class="overlay-body">
            <ul class="settings-list" style="list-style: none; padding: 0;">
                
                <!-- Group Settings -->
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn open-settings" id="group-settings-btn" data-grpchat-id="{{ $grpchat->id }}">
                        群组设置
                    </button>
                </li>
                <hr>
                <!-- Mute Members -->
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn open-mute-settings" data-grpchat-id="{{ $grpchat->id }}">
                        禁言成员
                    </button>
                </li>
                <hr>
                <!-- View Members -->
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn open-member-list" data-grpchat-id="{{ $grpchat->id }}">
                        查看成员
                    </button>
                </li>
                <hr>
                <!-- Invite Members -->
                <li class="settings-item">
                    <button type="button" class="btn btn-link settings-btn invite-members" data-grpchat-id="{{ $grpchat->id }}">
                        邀请成员
                    </button>
                </li>
                <hr>
                <!-- Pin Chat 
                <li class="settings-item">
                    <div class="toggle-label" style="display: flex; align-items: center; justify-content: space-between;">
                        置顶聊天
                        <label class="toggle-slider">
                            <input type="checkbox" id="pin-grpchat-toggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                </li>-->
                <hr>
            </ul>
            <!-- Quit Group -->
            <button type="button" class="btn btn-danger btndelete quit-group" data-grpchat-id="{{ $grpchat->id }}">退出群聊</button>
        </div>
    </div>
    @endisset
    
    <div id="myQRCodeModal" class="modal fade">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">我的二维码</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrCodeContainer"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="categoriesModal" tabindex="-1" aria-labelledby="categoriesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoriesModalLabel">查看分组</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="categories-treeview">
                        <!-- Treeview will be dynamically injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- History Modal Overlay -->
    <div id="history-modal" class="overlay-menu d-none">
      <div class="overlay-header">
        <span>历史记录</span>
        <button type="button" class="close-overlay" id="close-history-modal">&times;</button>
      </div>
      <!-- Tab Buttons -->
      <div class="overlay-tabs">
        <button class="tab-button active" data-target="photos-tab">照片</button>
        <button class="tab-button" data-target="videos-tab">视频</button>
        <button class="tab-button" data-target="files-tab">档案</button>
      </div>
      <!-- Tab Contents -->
      <div class="overlay-content">
        <!-- 照片 Tab -->
        <div class="tab-content active" id="photos-tab">
          <div class="items-grid">
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
          </div>
        </div>
        <!-- 视频 Tab -->
        <div class="tab-content" id="videos-tab">
          <div class="items-grid">
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
          </div>
        </div>
        <!-- 档案 Tab -->
        <div class="tab-content" id="files-tab">
          <div class="items-grid">
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
            <div class="mock-box"></div>
          </div>
        </div>
      </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
        <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.12.1/echo.iife.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/lightgallery@2.8.2/lightgallery.min.js"></script>
        <!-- LightGallery Plugins -->
        <script src="https://cdn.jsdelivr.net/npm/lightgallery@2.8.2/plugins/zoom/lg-zoom.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/lightgallery@2.8.2/plugins/rotate/lg-rotate.min.js"></script>
        <!-- Bootstrap Treeview CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.css" rel="stylesheet">
        
        <!-- Bootstrap Treeview JS -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-treeview/1.2.0/bootstrap-treeview.min.js"></script>

        
        <script type="module">
            import { EmojiButton } from 'https://unpkg.com/@joeattardi/emoji-button@4.3.0/dist/index.js';
            window.EmojiButton = EmojiButton;
        </script>
        
        
        
        <script src="{{ asset('js/chat.js') }}"></script>
    </x-slot:footerFiles>
    
</x-base-layout>