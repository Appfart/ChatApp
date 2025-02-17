<?php

namespace App\Http\Controllers;

use App\Models\Grpchat;
use App\Models\GrpChatSetting;
use App\Models\Grpmessage;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Friendlist;
use App\Models\Remark;
use App\Models\Message;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Events\NewGroupCreated;
use App\Events\GroupMemberUpdated;

class GrpchatController extends Controller
{

    private function getUserId(Request $request)
    {
        $token = $request->input('impersonation_token');
        return session()->has("impersonation_{$token}") 
            ? session("impersonation_{$token}")['robot_id'] ?? auth()->id()
            : auth()->id();
    }

    public function createGrpChat(Request $request)
    {
        Log::info('收到创建群聊的请求。', ['request' => $request->all()]);
        $userId = $this->getUserId($request);
        
        $request->validate([
            'chatname' => 'required|string|max:255',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);
    
        $members = array_unique($request->members);

        if (!in_array($userId, $members)) {
            $members[] = $userId;
        }

        $members = array_map('strval', $members);
        $ownerId = strval($userId);

        $admins = [ $ownerId ];

        try {
            $grpchat = Grpchat::create([
                'chatname' => $request->chatname,
                'members' => $members,
                'quitmembers' => [],
                'admins' => $admins,
                'owner' => $ownerId,
            ]);
    
            Log::info('群聊创建成功。', ['grpchat' => $grpchat]);
            
        } catch (\Exception $e) {
            Log::error('创建群聊时出错。', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => '群聊创建失败。',
            ], 500);
        }
        
        // 创建 GrpChatSetting 记录
        $grpChatSetting = GrpChatSetting::create([
            'grpchat_id' => $grpchat->id,
            'add_friend' => 1,
            'hide_members' => 0,
            'hide_allmembers' => 0,
            'allow_invite' => 1,
            'allow_qrinvite' => 1,
            'kyc' => 0,
            'block_quit' => 0,
            'mute_chat' => 0,
            'mute_members' => null, 
        ]);
    
        // 发送第一条欢迎消息到群组
        $welcomeMessage = "欢迎加入群聊【{$grpchat->chatname}】";

        try {
            $grpmessage = Grpmessage::create([
                'grpchat_id' => $grpchat->id,
                'user_id' => $ownerId, // 所有者发送消息
                'message' => $welcomeMessage,
                'image_url' => null,
                'audio_url' => null,
                'doc_url' => null,
            ]);
    
            Log::info('欢迎消息已发送。', ['grpmessage' => $grpmessage]);
        } catch (\Exception $e) {
            Log::error('发送欢迎消息时出错。', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => '群聊已创建，但发送欢迎消息失败。',
            ], 500);
        }
    
        Log::info('广播 NewGroupCreated 事件', [
            'group'   => $grpchat,
            'members' => $members,
        ]);
        
        broadcast(new NewGroupCreated($grpchat, $members));
        
        $response = [
            'status' => 'success',
            'message' => '群聊创建成功。',
            'grpchat' => [
                'id' => $grpchat->id,
                'chatname' => $grpchat->chatname,
                'avatar' => $grpchat->avatar, // 确保 Grpchat 模型中存在 'avatar'
            ],
            'latest_message' => [
                'message' => $grpmessage->message,
                'created_at' => $grpmessage->created_at->toDateTimeString(),
            ],
        ];
    
        return response()->json($response);
    }
    
    public function quitGrpChat(Request $request, $grpchatId)
    {
        $grpchat = Grpchat::findOrFail($grpchatId);

        $userId = $this->getUserId($request);
        
        // 检查是否允许退出
        $settings = $grpchat->settings; // 假设你已经为 settings 定义了关系
        if ($settings->block_quit == 1) {
            return response()->json(['error' => '退出此群聊已被禁用。'], 403);
        }
        
        $members = $grpchat->members;
        $quitmembers = $grpchat->quitmembers;
        
        if ($userId == $grpchat->owner) {
            Log::warning('尝试移除群组所有者', [
                'attempted_by' => $userId,
                'owner_id' => $grpchat->owner,
                'grpchat_id' => $grpchatId,
            ]);
            return response()->json(['error' => '无法移除群组所有者。'], 400);
        }

        if (!in_array($userId, $members)) {
            return response()->json(['message' => '您不是此群组的成员。'], 403);
        }

        // 从成员中移除用户
        $updatedMembers = array_filter($members, function ($member) use ($userId) {
            return $member != $userId;
        });
        
        // 重新索引数组以确保键连续
        $grpchat->members = array_values($updatedMembers);

        // 将用户添加到 quitmembers 并记录时间戳
        $quitmembers[] = [
            'id' => $userId,
            'timestamp' => now(),
        ];
        $grpchat->quitmembers = $quitmembers;

        $grpchat->save();

        return response()->json(['message' => '您已成功退出群聊。']);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'grpchat_id' => 'required|exists:grpchats,id',
            'message' => 'nullable|string',
            'image_url' => 'nullable|string',
            'audio_url' => 'nullable|string',
            'doc_url' => 'nullable|string',
        ]);
    
        $grpchat = Grpchat::findOrFail($request->grpchat_id);
    
        $settings = $grpchat->settings;

        // 检查是否全体静音
        if ($settings->mute_chat == 1) {
            return response()->json(['error' => '此群聊已对所有成员静音。'], 403);
        }
    
        // 检查当前用户是否被禁言
        if (in_array($userId, $settings->mute_members ?? [])) {
            return response()->json(['error' => '您在此群聊中已被禁言。'], 403);
        }
    
        if (!in_array($userId, $grpchat->members)) {
            return response()->json(['message' => '您不是此群组的成员。'], 403);
        }
    
        $grpmessage = Grpmessage::create([
            'grpchat_id' => $grpchat->id,
            'user_id' => $userId,
            'message' => $request->message,
            'image_url' => $request->image_url,
            'audio_url' => $request->audio_url,
            'doc_url' => $request->doc_url,
        ]);
    
        return response()->json([
            'status' => 'success',
            'grpmessage' => $grpmessage,
        ]);
    }

    public function getMessages(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
    
        if (!$userId) {
            return response()->json(['error' => '未授权的操作。'], 403);
        }
    
        // 获取群聊并确保用户是成员
        $grpchat = Grpchat::findOrFail($grpchatId);
    
        if (!in_array($userId, $grpchat->members)) {
            return response()->json(['message' => '您不是此群组的成员。'], 403);
        }
    
        // 获取群聊的所有消息
        $messages = Grpmessage::where('grpchat_id', $grpchat->id)
            ->with('user:id,name') // 为每条消息包含用户详情
            ->orderBy('created_at', 'asc')
            ->get();
    
        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function getFriendList(Request $request)
    {
        // 记录函数开始
        Log::info('调用 getFriendList。');
    
        $userId = $this->getUserId($request);
    
        try {
            // 从数据库获取好友列表
            $friendList = DB::table('friendlists')
                ->where('status', 2)
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->get(['user_id', 'friend_id']);
    
            Log::info('获取到的好友列表：', ['friendList' => $friendList]);
    
            // 提取好友 ID
            $friendIds = $friendList->map(function ($friend) use ($userId) {
                return $friend->user_id == $userId ? $friend->friend_id : $friend->user_id;
            })->unique()->values();
    
            Log::info('提取的好友 ID：', ['friendIds' => $friendIds]);
    
            if ($friendIds->isEmpty()) {
                Log::info('用户没有好友。');
                return response()->json(['friends' => []]);
            }
    
            // 获取好友的用户详情
            $friends = User::whereIn('id', $friendIds)->get(['id', 'name', 'realname']);
    
            Log::info('获取到的好友详情：', ['friends' => $friends]);
    
            // 以 JSON 返回好友
            return response()->json(['friends' => $friends]);
    
        } catch (\Exception $e) {
            // 记录任何发生的异常
            Log::error('获取好友列表时出错：', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => '获取好友列表时发生错误。'
            ], 500);
        }
    }
    
    public function settings(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
    
        // Fetch the group chat details with settings
        $grpchat = Grpchat::with('settings')->findOrFail($grpchatId);
    
        // Check if the user is a member of the group
        if (!in_array($userId, $grpchat->members)) {
            return response()->json(['error' => '未授权：您不是此群组的成员。'], 403);
        }
    
        // Determine if the user is the owner
        $isOwner = $grpchat->owner == $userId;
    
        // Determine if the user is an admin
        $isAdmin = in_array($userId, $grpchat->admins);
    
        // Fetch nicknames for admins and members
        $remarks = Remark::where('user_id', $userId)
                        ->whereIn('target_id', array_merge($grpchat->admins, $grpchat->members))
                        ->get()
                        ->keyBy('target_id'); // Key by target_id for easy access
    
        // Fetch admin details
        $admins = User::whereIn('id', $grpchat->admins)->get(['id', 'name', 'realname']);
        $adminDetails = $admins->map(function ($admin) use ($remarks) {
            $nickname = $remarks->has($admin->id) ? $remarks->get($admin->id)->nickname : null;
    
            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'realname' => $admin->realname ?? 'N/A',
                'nickname' => $nickname ?? $admin->realname ?? $admin->name,
            ];
        });
    
        // Fetch member details
        $members = User::whereIn('id', $grpchat->members)->get(['id', 'name', 'realname']);
        $memberDetails = $members->map(function ($member) use ($remarks) {
            $nickname = $remarks->has($member->id) ? $remarks->get($member->id)->nickname : null;
    
            return [
                'id' => $member->id,
                'name' => $member->name,
                'realname' => $member->realname ?? 'N/A',
                'nickname' => $nickname ?? $member->realname ?? $member->name,
            ];
        });
    
        // Base response for all group members
        $response = [
            'chatname' => $grpchat->chatname,
            'avatar' => $grpchat->avatar,
            'announcement' => $grpchat->announcement,
            'owner' => $grpchat->owner,
            'is_owner' => $isOwner,
            'is_admin' => $isAdmin,
        ];
    
        // Include additional settings for admins
        if ($isAdmin) {
            $response['settings'] = $grpchat->settings;
            $response['admins'] = $adminDetails;
            $response['members'] = $memberDetails;
        }
    
        return response()->json($response);
    }
    
    public function updateSettings(Request $request)
    {
        $userId = $this->getUserId($request);
    
        Log::info('启动更新设置请求', [
            'user_id' => $userId,
            'request_data' => $request->all(),
        ]);
    
        $request->validate([
            'grpchat_id' => 'required|exists:grpchats,id',
            'chatname' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:2048',
            'announcement' => 'nullable|string|max:2000',
            'add_friend' => 'nullable|boolean',
            'hide_members' => 'nullable|boolean',
            'allow_invite' => 'nullable|boolean',
            'block_quit' => 'nullable|boolean',
            'mute_chat' => 'nullable|boolean',
        ]);
    
        $grpchat = Grpchat::findOrFail($request->grpchat_id);
    
        // 记录更新前的群聊详情
        Log::info('获取到的群聊详情', [
            'grpchat_id' => $grpchat->id,
            'current_settings' => $grpchat->settings,
            'admins' => $grpchat->admins,
        ]);
    
        // 检查用户是否为管理员
        if (!in_array($userId, $grpchat->admins)) {
            Log::warning('未授权访问尝试', [
                'grpchat_admins' => $grpchat->admins,
                'request_user' => $userId,
            ]);
            return response()->json(['error' => '未授权。'], 403);
        }
    
        try {
            // 更新设置
            $updatedSettings = [
                'add_friend' => $request->add_friend,
                'hide_members' => $request->hide_members,
                'allow_invite' => $request->allow_invite,
                'block_quit' => $request->block_quit,
                'mute_chat' => $request->mute_chat,
            ];
    
            $grpchat->settings()->update($updatedSettings);
            Log::info('设置已更新', [
                'updated_settings' => $updatedSettings,
            ]);
    
            if ($request->has('chatname')) {
                Log::info('更新群聊名称', [
                    'old_chatname' => $grpchat->chatname,
                    'new_chatname' => $request->chatname,
                ]);
                $grpchat->chatname = $request->chatname;
            }
    
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                Log::info('更新头像', [
                    'old_avatar' => $grpchat->avatar,
                    'new_avatar' => $avatarPath,
                ]);
                $grpchat->avatar = $avatarPath;
            }
    
            if ($request->has('announcement')) {
                Log::info('更新公告', [
                    'old_announcement' => $grpchat->announcement,
                    'new_announcement' => $request->announcement,
                ]);
                $grpchat->announcement = $request->announcement;
            }
    
            $grpchat->save();
    
            // 获取更新后的管理员
            $admins = User::whereIn('id', $grpchat->admins)->get(['id', 'name', 'realname']);
    
            Log::info('设置更新成功', [
                'grpchat' => $grpchat->toArray(),
                'admins' => $admins->toArray(),
            ]);
    
            return response()->json([
                'message' => '设置已成功更新。',
                'grpchat' => [
                    'chatname' => $grpchat->chatname,
                    'avatar' => $grpchat->avatar,
                    'owner' => $grpchat->owner,
                    'announcement' => $grpchat->announcement,
                    'settings' => $grpchat->settings,
                    'admins' => $admins,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('更新设置时出错', [
                'error_message' => $e->getMessage(),
                'grpchat_id' => $grpchat->id,
                'request_data' => $request->all(),
            ]);
            return response()->json(['error' => '无法更新设置。'], 500);
        }
    }
    
    public function updateMuteMembers(Request $request)
    {
        $userId = $this->getUserId($request);
    
        $request->validate([
            'grpchat_id' => 'required|exists:grpchats,id',
            'mute_members' => 'nullable|array',
            'mute_members.*' => 'exists:users,id',
        ]);
    
        $grpchat = Grpchat::findOrFail($request->grpchat_id);
    
        // 检查认证用户是否为群组所有者或管理员
        if (!in_array($userId, $grpchat->admins)) {
            Log::warning('未授权访问尝试', [
                'grpchat_admins' => $grpchat->admins,
                'request_user' => $userId,
            ]);
            return response()->json(['error' => '未授权。'], 403);
        }
    
        // 更新 settings 中的 mute_members 字段
        $settings = $grpchat->settings;
    
        if ($settings) {
            $settings->update([
                'mute_members' => $request->mute_members ?? [],
            ]);
        } else {
            Log::error('未找到群组设置，grpchat_id: ' . $grpchat->id);
            return response()->json(['error' => '未找到群组设置。'], 404);
        }
    
        Log::info('已成功更新被禁言的成员', [
            'grpchat_id' => $grpchat->id,
            'mute_members' => $request->mute_members,
        ]);
    
        return response()->json(['message' => '已成功更新被禁言的成员。']);
    }
    
    public function removeMember(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
    
        try {
            $grpchat = Grpchat::findOrFail($grpchatId);
            Log::info('成功获取群聊', [
                'grpchat_id' => $grpchatId,
                'grpchat_details' => $grpchat->toArray(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('未找到群聊', [
                'grpchat_id' => $grpchatId,
                'error_message' => $e->getMessage(),
            ]);
            return response()->json(['error' => '未找到群聊。'], 404);
        }
    
        $memberId = $request->input('member_id');

        // 检查用户是否为管理员
        if (!in_array($userId, $grpchat->admins)) {
            Log::warning('未授权访问尝试', [
                'grpchat_admins' => $grpchat->admins,
                'request_user' => $userId,
            ]);
            return response()->json(['error' => '未授权。'], 403);
        }
    
        // 防止移除所有者
        if ($memberId == $grpchat->owner) {
            Log::warning('尝试移除群组所有者', [
                'attempted_by' => $userId,
                'owner_id' => $grpchat->owner,
                'grpchat_id' => $grpchatId,
            ]);
            return response()->json(['error' => '无法移除群组所有者。'], 400);
        }
    
        // 从成员数组中移除成员
        $originalMembers = $grpchat->members;
        $updatedMembers = array_values(array_diff($grpchat->members, [$memberId]));
    
        if ($originalMembers == $updatedMembers) {
            Log::warning('尝试移除不存在的成员', [
                'member_id' => $memberId,
                'grpchat_id' => $grpchatId,
                'current_members' => $originalMembers,
            ]);
            return response()->json(['error' => '未在群组中找到该成员。'], 404);
        }
    
        $grpchat->members = $updatedMembers;
    
        // 在 quitmembers 中记录被移除的成员
        $quitmembers = $grpchat->quitmembers ?? [];
        $quitmembers[] = [
            'id' => $memberId,
            'timestamp' => now(),
        ];
        $grpchat->quitmembers = $quitmembers;
    
        // 保存更新后的群聊
        $grpchat->save();
        
        broadcast(new GroupMemberUpdated($grpchat, $updatedMembers));
        
        Log::info('成员已成功移除', [
            'removed_member_id' => $memberId,
            'updated_members' => $updatedMembers,
            'quitmembers_log' => $quitmembers,
            'grpchat_id' => $grpchatId,
            'action_performed_by' => $userId,
        ]);
    
        return response()->json(['message' => '成员已成功移除。']);
    }
    
    public function addMember(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
        Log::info('Fetched User ID: ' . $userId);
    
        $grpchat = Grpchat::findOrFail($grpchatId);
    
        $settings = $grpchat->settings;
        
        // 检查用户是否为所有者或管理员
        $isOwner = $userId === $grpchat->owner;
        $isAdmin = in_array((string)$userId, $grpchat->admins ?? []);
    
        // 所有者或管理员跳过 allow_invite 检查
        if (!$isOwner && !$isAdmin && $grpchat->settings->allow_invite == 0) {
            return response()->json(['status' => 'error', 'message' => '该群组已禁用添加新成员。'], 403);
        }
    
        // 验证输入
        $request->validate([
            'member_id' => 'required|exists:users,id',
        ]);
    
        $memberId = $request->input('member_id');
    
        // 检查用户是否已经是群组成员
        if (in_array($memberId, $grpchat->members ?? [])) {
            Log::warning('用户已经是群组成员', [
                'grpchatId' => $grpchatId,
                'memberId' => $memberId,
                'current_members' => $grpchat->members,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => '用户已经是该群组的成员。',
            ], 400);
        }
    
        // 添加成员到群组
        $members = $grpchat->members ?? [];
        $members[] = (string) $memberId; // 转换为字符串后添加
    
        // 确保所有成员是字符串
        $members = array_map('strval', $members);
        $grpchat->members = $members;
    
        // 如果在 quitmembers 中存在则移除
        $quitmembers = $grpchat->quitmembers ?? [];
        $updatedQuitmembers = array_filter($quitmembers, function ($quitMember) use ($memberId) {
            return (string) $quitMember['id'] != (string) $memberId; // 按字符串比较
        });
        $grpchat->quitmembers = array_values($updatedQuitmembers);
    
        Log::info('重新添加成员后更新 quitmembers', [
            'grpchatId' => $grpchatId,
            'updated_quitmembers' => $grpchat->quitmembers,
        ]);
    
        // 保存更新后的群组
        try {
            $grpchat->save();
            Log::info('成功更新群组后添加成员', [
                'grpchatId' => $grpchatId,
                'added_member_id' => $memberId,
                'updated_members' => $grpchat->members,
            ]);
        } catch (\Exception $e) {
            Log::error('保存更新后的群组失败', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => '添加成员到群组失败。'], 500);
        }
    
        $newMember = User::findOrFail($memberId);
        broadcast(new NewGroupCreated($grpchat, $members));
    
        return response()->json([
            'status' => 'success',
            'message' => '成员添加成功。',
            'new_member' => [
                'id' => $newMember->id,
                'name' => $newMember->name,
                'realname' => $newMember->realname,
            ],
        ]);
    }

    public function getAvailableMembers(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
    
        try {
            // Step 1: Fetch the friend list
            $friendList = DB::table('friendlists')
                ->where('status', 2) // Status 2 indicates confirmed friendship
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhere('friend_id', $userId);
                })
                ->get(['user_id', 'friend_id']);
    
            // Extract friend IDs
            $friendIds = $friendList->map(function ($friend) use ($userId) {
                return $friend->user_id == $userId ? $friend->friend_id : $friend->user_id;
            })->unique()->values();
    
            if ($friendIds->isEmpty()) {
                return response()->json(['available_members' => []]);
            }
    
            // Step 2: Fetch group details
            $grpchat = Grpchat::findOrFail($grpchatId);
            $currentMembers = collect($grpchat->members ?? []);
            $quitMembers = collect($grpchat->quitmembers ?? [])->pluck('id');
    
            // Step 3: Filter out friends who are already members
            $availableMemberIds = $friendIds->filter(function ($friendId) use ($currentMembers) {
                return !$currentMembers->contains($friendId); // Exclude current members
            });
    
            // Include members who have quit (allow re-adding)
            $finalAvailableIds = $availableMemberIds->merge($quitMembers)->unique();
    
            // Fetch available members' details
            $availableMembers = User::whereIn('id', $finalAvailableIds)->get(['id', 'name', 'realname']);
    
            // Fetch nicknames from the remarks table
            $remarks = Remark::where('user_id', $userId)
                            ->whereIn('target_id', $finalAvailableIds)
                            ->get()
                            ->keyBy('target_id'); // Key by target_id for easy access
    
            // Add nicknames to available members
            $memberDetails = $availableMembers->map(function ($member) use ($remarks) {
                $nickname = $remarks->has($member->id) ? $remarks->get($member->id)->nickname : null;
    
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'realname' => $member->realname ?? 'N/A',
                    'nickname' => $nickname ?? $member->realname ?? $member->name, // Use nickname if available
                ];
            });
    
            // Return the results
            return response()->json(['available_members' => $memberDetails]);
    
        } catch (\Exception $e) {
            Log::error('Error fetching available members:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch available members.'], 500);
        }
    }
    
    public function getAnnouncement(Request $request, $grpchatId)
    {
        $grpchat = Grpchat::findOrFail($grpchatId);
    
        return response()->json(['announcement' => $grpchat->announcement ?? '暂无公告']);
    }
    
    // Flutter API
    public function getMemberList(Request $request, $grpchatId)
    {
        // Find the group chat
        $grpchat = Grpchat::findOrFail($grpchatId);
    
        // Get the user ID of the requester
        $userId = $this->getUserId($request);
    
        // Check if the user is the owner or an admin
        $isOwner = $userId === $grpchat->owner;
        $isAdmin = in_array((string)$userId, $grpchat->admins ?? []);
    
        // If not the owner or admin, check if members are hidden
        if (!$isOwner && !$isAdmin && $grpchat->settings->hide_members == 1) {
            return response()->json(['error' => '此群组已禁用查看成员列表功能。'], 403);
        }
    
        // Check if the user is a member of the group chat
        if (!in_array((string)$userId, $grpchat->members ?? [])) {
            return response()->json(['message' => '您不是此群聊的成员。'], 403);
        }
    
        // Fetch all group members
        $members = User::whereIn('id', $grpchat->members ?? [])->get(['id', 'name', 'avatar', 'referral_link', 'realname']);
    
        // Fetch nicknames from the remarks table
        $remarks = Remark::where('user_id', $userId) // Fetch remarks created by the requester
                        ->whereIn('target_id', $grpchat->members ?? [])
                        ->get()
                        ->keyBy('target_id'); // Key by target_id for easy access
    
        // Assemble the member details with nicknames
        $memberDetails = $members->map(function ($member) use ($remarks) {
            // Get the nickname if it exists
            $nickname = $remarks->has($member->id) ? $remarks->get($member->id)->nickname : null;
    
            return [
                'id' => $member->id,
                'name' => $member->name,
                'realname' => $member->realname ?? 'N/A',
                'nickname' => $nickname ?? $member->realname ?? $member->name, // Use nickname if available
                'avatar_url' => $member->avatar,
                'referral_link' => $member->referral_link,
            ];
        });
    
        return response()->json([
            'members' => $memberDetails,
            'owner_id' => $grpchat->owner,
        ]);
    }

    public function grpSettings(Request $request, $grpchatId)
    {
        $userId = $this->getUserId($request);
            
        $isGroupChat = filter_var($request->query('isGroupChat', false), FILTER_VALIDATE_BOOLEAN);

        Log::debug('已转换 isGroupChat', [
            'isGroupChat' => $isGroupChat,
        ]);

        if ($isGroupChat) {
            Log::info('作为群组处理', [
                'grpchatId' => $grpchatId,
                'userId' => $userId,
            ]);

            try {
                // 现有的群组聊天逻辑
                $grpchat = Grpchat::findOrFail($grpchatId);
                Log::debug('找到群聊', ['grpchat' => $grpchat->toArray()]);

                $members = User::whereIn('id', $grpchat->members)->get(['id', 'name']);
                Log::debug('获取到群聊成员', ['members' => $members->toArray()]);

                $isOwner = $grpchat->owner == $userId;
                Log::debug('所有权状态', ['isOwner' => $isOwner]);

                Log::info('群聊设置响应', [
                    'grpchat' => $grpchat->toArray(),
                    'members' => $members->toArray(),
                    'isOwner' => $isOwner,
                ]);

                return response()->json([
                    'chatname' => $grpchat->chatname,
                    'members' => $members,
                    'avatar' => $grpchat->avatar,
                    'is_owner' => $isOwner,
                ]);
            } catch (\Exception $e) {
                Log::error('处理群聊设置时出错', [
                    'grpchatId' => $grpchatId,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'error' => '无法检索群聊设置。'
                ], 500);
            }
        } else {
            Log::info('作为普通聊天处理', [
                'grpchatId' => $grpchatId,
                'userId' => $userId,
            ]);

            try {
                // 新的普通（单对单）聊天逻辑
                // 获取对话，不期望有 'members' 关系
                $conversation = Conversation::findOrFail($grpchatId);
                Log::debug('找到对话', ['conversation' => $conversation->toArray()]);

                $chatName = '';
                $avatarUrl = '';
                $members = [];

                // 验证 'name' 和 'target' 是否存在且指向不同用户
                if (empty($conversation->name) || empty($conversation->target)) {
                    Log::warning('对话缺少名称或目标', [
                        'conversationId' => $grpchatId,
                        'name' => $conversation->name,
                        'target' => $conversation->target,
                    ]);
                    return response()->json([
                        'error' => '无效的对话数据。'
                    ], 400);
                }

                if ($conversation->name == $conversation->target) {
                    Log::warning('对话中名称和目标用户相同', [
                        'conversationId' => $grpchatId,
                        'name' => $conversation->name,
                        'target' => $conversation->target,
                    ]);
                    return response()->json([
                        'error' => '对话成员无效。'
                    ], 400);
                }

                // 获取参与对话的两个用户
                $user1 = User::find($conversation->name);
                $user2 = User::find($conversation->target);

                if (!$user1 || !$user2) {
                    Log::warning('对话中的一个或两个用户未找到', [
                        'conversationId' => $grpchatId,
                        'user1Id' => $conversation->name,
                        'user2Id' => $conversation->target,
                    ]);
                    return response()->json([
                        'error' => '未找到对话参与者。'
                    ], 404);
                }

                // 确定另一个参与者
                if ($user1->id == $userId) {
                    $otherUser = $user2;
                } else if ($user2->id == $userId) {
                    $otherUser = $user1;
                } else {
                    // 认证用户不是此对话的参与者
                    Log::warning('认证用户不是对话的参与者', [
                        'conversationId' => $grpchatId,
                        'userId' => $userId,
                    ]);
                    return response()->json([
                        'error' => '您不是此对话的参与者。'
                    ], 403);
                }

                $chatName = $otherUser->name;
                $avatarUrl = $otherUser->avatar ?? '';

                // 构建包含两个用户的成员数组
                $members = [
                    [
                        'id' => $user1->id,
                        'name' => $user1->name,
                        'avatar' => $user1->avatar ?? '',
                    ],
                    [
                        'id' => $user2->id,
                        'name' => $user2->name,
                        'avatar' => $user2->avatar ?? '',
                    ],
                ];

                Log::debug('确定了另一个参与者', [
                    'chatName' => $chatName,
                    'avatarUrl' => $avatarUrl,
                    'members' => $members,
                ]);

                Log::info('普通聊天设置响应', [
                    'conversation' => $conversation->toArray(),
                    'members' => $members,
                    'isOwner' => true, // 在普通聊天中，请求者被视为所有者
                ]);

                return response()->json([
                    'chatname' => $chatName,
                    'members' => $members,
                    'avatar' => $avatarUrl,
                    'is_owner' => true, // 请求者在一对一聊天中是所有者
                ]);
            } catch (\Exception $e) {
                Log::error('处理普通聊天设置时出错', [
                    'grpchatId' => $grpchatId,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'error' => '无法检索普通聊天设置。'
                ], 500);
            }
        }
    }
    
    public function addAdmin(Request $request)
    {
        $request->validate([
            'grpchat_id' => 'required|exists:grpchats,id',
            'admin_id' => 'required|exists:users,id',
        ]);
    
        $userId = $this->getUserId($request);
    
        $grpchat = Grpchat::findOrFail($request->grpchat_id);
        
        if ($grpchat->owner != $userId && !$grpchat->isAdmin($userId)) {
            return response()->json(['status' => 'error', 'message' => '您没有权限执行此操作。'], 403);
        }
    
        // 检查用户是否已经是管理员
        if ($grpchat->isAdmin($request->admin_id)) {
            return response()->json(['status' => 'error', 'message' => '该用户已是管理员。'], 400);
        }
    
        // 将用户添加为管理员
        $grpchat->addAdmin($request->admin_id);
    
        $user = User::findOrFail($request->admin_id);
        
        broadcast(new NewGroupCreated($grpchat, [(string) $user->id]));
    
        return response()->json([
            'status' => 'success',
            'message' => '管理员添加成功。',
            'new_admin' => [
                'id' => $user->id,
                'name' => $user->name,
                'realname' => $user->realname,
            ],
        ]);
    }
    
    public function removeAdmin(Request $request, $grpchatId)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);
    
        $userId = $this->getUserId($request);
    
        // 获取群聊
        try {
            $grpchat = Grpchat::findOrFail($grpchatId);
            Log::info('成功获取群聊', [
                'grpchat_id' => $grpchatId,
                'grpchat_details' => $grpchat->toArray(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('未找到群聊', [
                'grpchat_id' => $grpchatId,
                'error_message' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => '群聊不存在。'], 404);
        }
    
        // 检查当前用户是否为所有者
        if ($grpchat->owner != $userId && !$grpchat->isAdmin($userId)) {
            Log::warning('尝试未授权移除管理员', [
                'grpchat_id' => $grpchat->id,
                'owner_id' => $grpchat->owner,
                'request_user' => $userId,
            ]);
            return response()->json(['status' => 'error', 'message' => '您没有权限执行此操作。'], 403);
        }
        
        // 检查是否尝试移除群主
        if ($grpchat->owner == $request->admin_id) {
            Log::warning('尝试移除群主为管理员', [
                'grpchat_id' => $grpchat->id,
                'owner_id' => $grpchat->owner,
                'attempted_remove_id' => $request->admin_id,
                'request_user' => $userId,
            ]);
            return response()->json(['status' => 'error', 'message' => '无法移除群主的管理员权限。'], 400);
        }
    
        // 从管理员数组中移除用户
        try {
            $grpchat->removeAdmin($request->admin_id);
            Log::info('成功移除管理员', [
                'grpchat_id' => $grpchat->id,
                'removed_admin_id' => $request->admin_id,
                'updated_admins' => $grpchat->admins,
            ]);
        } catch (\Exception $e) {
            Log::error('移除管理员时出错', [
                'grpchat_id' => $grpchat->id,
                'admin_id' => $request->admin_id,
                'error_message' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error', 'message' => '无法移除管理员。'], 500);
        }
    
        // 获取已移除管理员的用户实例
        $admin = User::findOrFail($request->admin_id);
    
        return response()->json([
            'status' => 'success',
            'message' => '管理员移除成功。',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'realname' => $admin->realname, // 根据你的 User 模型属性调整
            ],
        ]);
    }
    
    public function getAdmins($grpchatId)
    {
        try {
            $grpchat = Grpchat::findOrFail($grpchatId);

            $admins = $grpchat->getAdmins()->map(function ($admin) {
                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'realname' => $admin->realname,
                ];
            });
    
            $nonAdminUsers = User::whereNotIn('id', $grpchat->admins ?? [])->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'realname' => $user->realname,
                ];
            });
    
            return response()->json([
                'admins' => $admins,
                'non_admin_users' => $nonAdminUsers,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAdmins method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }
    
    public function getMembers(Request $request, $grpchatId)
    {
        $grpchat = Grpchat::findOrFail($grpchatId);
    
        $currentMembers = $grpchat->members ?? [];
        $quitMembers = collect($grpchat->quitmembers ?? [])->pluck('id');
    
        // 排除当前成员和已退出成员
        $availableMembers = User::whereNotIn('id', array_merge($currentMembers, $quitMembers->toArray()))->get();
    
        return response()->json([
            'available_members' => $availableMembers->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'realname' => $user->realname,
            ]),
            'current_members' => User::whereIn('id', $currentMembers)->get()->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'realname' => $user->realname,
            ]),
        ]);
    }
    
    public function availableAdmin(Request $request, $grpchatId)
    {
        try {
            // Step 1: Fetch the group chat
            $grpchat = Grpchat::findOrFail($grpchatId);
    
            // Step 2: Retrieve members and admins
            $members = $grpchat->members ?? []; // Members of the group
            $admins = $grpchat->admins ?? [];   // Admins of the group
    
            Log::info('Fetched group details', [
                'grpchat_id' => $grpchatId,
                'members' => $members,
                'admins' => $admins,
            ]);
    
            // Step 3: Filter members who are NOT admins
            $availableAdminIds = collect($members)->filter(function ($memberId) use ($admins) {
                return !in_array($memberId, $admins); // Include only non-admin members
            });
    
            Log::info('Filtered available admin IDs', [
                'available_admin_ids' => $availableAdminIds,
            ]);
    
            // Step 4: Fetch user details for available admin IDs
            $availableAdmins = User::whereIn('id', $availableAdminIds)->get(['id', 'name', 'realname']);
    
            Log::info('Available admins fetched', [
                'available_admins' => $availableAdmins,
            ]);
    
            // Return the list of available admins
            return response()->json(['available_admins' => $availableAdmins]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Group chat not found', [
                'grpchat_id' => $grpchatId,
                'error_message' => $e->getMessage(),
            ]);
    
            return response()->json([
                'error' => 'Group chat not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error in availableAdmin method', [
                'grpchat_id' => $grpchatId,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'error' => 'Failed to fetch available admins.',
            ], 500);
        }
    }
    
    public function getHistory($chatId, Request $request)
    {
        // Determine chat type. Default is group chat.
        // To get conversation history, call the URL with ?chat_type=conversation
        $chatType = $request->input('chat_type', 'grpchat');

        // Use the appropriate model based on the chat type.
        if ($chatType === 'conversation') {
            $query = Message::query()->where('conversation_id', $chatId);
        } else {
            // For group chats, use the Grpmessage model.
            $query = Grpmessage::query()->where('grpchat_id', $chatId);
        }

        // For each media type, get the latest 50 messages (ordered by created_at descending)
        $images = (clone $query)
            ->whereNotNull('image_url')
            ->where('image_url', '<>', '')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get(['id', 'image_url', 'created_at']);

        $videos = (clone $query)
            ->whereNotNull('video_url')
            ->where('video_url', '<>', '')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get(['id', 'video_url', 'created_at']);

        $docs = (clone $query)
            ->whereNotNull('doc_url')
            ->where('doc_url', '<>', '')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get(['id', 'doc_url', 'created_at']);

        // Return the media grouped in JSON format
        return response()->json([
            'images' => $images,
            'videos' => $videos,
            'docs'   => $docs,
        ]);
    }
}
