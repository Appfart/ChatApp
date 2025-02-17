<?php

namespace App\Http\Controllers;
use Carbon\Carbon;

use App\Models\Friendlist;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Wallet;

use App\Models\Grpchat;
use App\Models\GrpChatSetting;
use App\Models\Remark;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Events\NewFriendRequestEvent;

class FriendController extends Controller
{
    protected function getCurrentUser(Request $request)
    {
        $token = $request->get('impersonation_token');

        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");

            if (isset($impersonationData['is_self']) && $impersonationData['is_self']) {

                $user = User::find($impersonationData['support_id']);

                if (!$user) {
                    Log::channel('robot')->warning('自我模拟时未找到用户', [
                        'support_id' => $impersonationData['support_id'],
                        'token' => $token,
                    ]);
                    abort(403, '未找到用户。');
                }

                return $user;
            } else {
                // **机器人模拟情况**
                $robotId = $impersonationData['robot_id'] ?? null;
                $supportId = $impersonationData['support_id'] ?? null;

                if (!$robotId || !$supportId) {
                    Log::channel('robot')->warning('模拟数据不完整', [
                        'impersonationData' => $impersonationData,
                        'token' => $token,
                    ]);
                    abort(403, '无效的模拟数据。');
                }

                // 检查机器人是否分配给支持用户
                $robotLink = \App\Models\RobotLink::where('support_id', $supportId)
                    ->where('robot_id', $robotId)
                    ->where('status', 1)
                    ->first();

                if (!$robotLink) {
                    Log::channel('robot')->warning('模拟失败：机器人未分配', [
                        'support_id' => $supportId,
                        'robot_id' => $robotId,
                        'token' => $token,
                    ]);
                    abort(403, '您无权模拟此机器人。');
                }

                // 模拟机器人
                Auth::onceUsingId($robotId);
                $user = auth()->user();

                if (!$user) {
                    Log::channel('robot')->warning('模拟机器人时未找到机器人用户', [
                        'robot_id' => $robotId,
                        'token' => $token,
                    ]);
                    abort(403, '未找到机器人用户。');
                }

                return $user;
            }
        }

        // **未提供模拟令牌**
        $user = auth()->user();
        return $user;
    }
    
    public function addFriend(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            Log::channel('robot')->warning('未授权访问尝试', [
                'request_data' => $request->all(),
            ]);
            return response()->json(['status' => 'error', 'message' => '未经授权的访问。'], 403);
        }

        $grpchatId = null;
        
        if ($request->filled('grpchat_id')) {
            $grpchatId = $request->input('grpchat_id');
            $grpchatParam = 'grpchat_id';
        } elseif ($request->filled('grpid')) {
            $grpchatId = $request->input('grpid');
            $grpchatParam = 'grpid';
        }

        $validationRules = [
            'referral_link' => 'required|exists:users,referral_link', // 确保推荐链接存在
        ];

        if ($grpchatId) {
            $validationRules[$grpchatParam] = 'nullable|exists:grpchats,id';
        }

        $validated = $request->validate($validationRules);
        
        if ($grpchatId) {

            try {
                // 预加载设置
                $grpchat = Grpchat::with('settings')->findOrFail($grpchatId);
            } catch (\Exception $e) {
                Log::channel('robot')->error('未找到群组', [
                    'grpchat_id' => $grpchatId,
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['status' => 'error', 'message' => '群组不存在。'], 404);
            }

            $settings = $grpchat->settings;

            // 如果此群组不允许添加好友
            if ($settings && $settings->add_friend == 0) {
                Log::channel('robot')->info('群组不允许添加好友', [
                    'grpchat_id' => $grpchat->id,
                    'settings' => $settings,
                ]);

                $memberId = $user->id; // 获取用户 ID
                
                $isOwnerOrAdmin = $grpchat->owner_id == $memberId || in_array($memberId, $grpchat->admins ?? []);

                // 如果用户不是所有者或管理员，则拒绝操作
                if (!$isOwnerOrAdmin) {
                    Log::channel('robot')->warning('非管理员或群主尝试添加好友', [
                        'user_id' => $memberId,
                        'grpchat_id' => $grpchat->id,
                    ]);

                    return response()->json(['status' => 'error', 'message' => '该群组不允许添加好友。'], 403);
                } else {
                    Log::channel('robot')->info('用户是管理员或群主，允许添加好友', [
                        'user_id' => $memberId,
                        'grpchat_id' => $grpchat->id,
                    ]);
                }
            } else {
                Log::channel('robot')->info('群组允许添加好友或未配置设置', [
                    'grpchat_id' => $grpchat->id,
                    'add_friend' => $settings->add_friend ?? '未配置',
                ]);
            }
        }
        
        $friend = User::where('referral_link', $validated['referral_link'])->first();

        if (!$friend) {
            Log::channel('robot')->warning('未找到好友', [
                'referral_link' => $validated['referral_link'],
            ]);
            return response()->json(['status' => 'error', 'message' => '未找到好友。'], 404);
        }

        if ($user->id === $friend->id) {
            Log::channel('robot')->warning('用户尝试将自己添加为好友', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => '您不能将自己添加为好友。',
            ], 400);
        }

        $existingFriendship = Friendlist::where(function ($query) use ($user, $friend) {
            $query->where('user_id', $user->id)->where('friend_id', $friend->id);
        })->orWhere(function ($query) use ($user, $friend) {
            $query->where('user_id', $friend->id)->where('friend_id', $user->id);
        })->first();
        
        if ($existingFriendship) {
            if ($existingFriendship->status == 3) {
                if ($existingFriendship->friend_id == $user->id) {
                    $existingFriendship->user_id = $user->id;
                    $existingFriendship->friend_id = $friend->id;
                }
            
                $existingFriendship->status = 1;
                $existingFriendship->save();
                
                // Trigger the event
                event(new NewFriendRequestEvent($friend->id));
            
                return response()->json([
                    'status' => 'success',
                    'message' => '好友请求已重新发送。',
                ]);
            }
        
            return response()->json([
                'status' => 'error',
                'message' => '好友请求已存在或您已是好友。',
            ], 400);
        }

        
        Friendlist::create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => 1,
        ]);
        
        event(new NewFriendRequestEvent($friend->id));

        return response()->json(['status' => 'success', 'message' => '好友请求发送成功。']);
    }

    private function getAuthenticatedUser(Request $request)
    {
        $token = $request->get('impersonation_token');

        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");

            if (isset($impersonationData['is_self']) && $impersonationData['is_self']) {

                $user = User::find($impersonationData['support_id']);

                if (!$user) {
                    Log::channel('robot')->warning('自我模拟时未找到用户', [
                        'support_id' => $impersonationData['support_id'],
                        'token' => $token,
                    ]);
                    return null;
                }

                return $user;
            } else {
                // **机器人模拟情况**
                $robotId = $impersonationData['robot_id'] ?? null;
                $supportId = $impersonationData['support_id'] ?? null;

                if (!$robotId || !$supportId) {
                    Log::channel('robot')->warning('模拟数据不完整', [
                        'impersonationData' => $impersonationData,
                        'token' => $token,
                    ]);
                    return null;
                }

                // 检查机器人是否分配给支持用户
                $robotLink = \App\Models\RobotLink::where('support_id', $supportId)
                    ->where('robot_id', $robotId)
                    ->where('status', 1)
                    ->first();

                if (!$robotLink) {
                    Log::channel('robot')->warning('模拟失败：机器人未分配', [
                        'support_id' => $supportId,
                        'robot_id' => $robotId,
                        'token' => $token,
                    ]);
                    return null;
                }

                // 模拟机器人
                Auth::onceUsingId($robotId);
                $user = auth()->user();

                if (!$user) {
                    Log::channel('robot')->warning('模拟机器人时未找到机器人用户', [
                        'robot_id' => $robotId,
                        'token' => $token,
                    ]);
                    return null;
                }

                /* Log::channel('robot')->info('用户已模拟为机器人', [
                    'support_id' => $supportId,
                    'robot_id' => $robotId,
                    'token' => $token,
                ]); */

                return $user;
            }
        }

        // **未提供模拟令牌**
        return auth()->user();
    }
    
    public function searchFriend(Request $request)
    {
        $user = $this->getCurrentUser($request);
        $userId = $user->id;

        $validated = $request->validate([
            'query' => 'required|string|min:1', // 确保查询不为空
        ]);

        $query = $validated['query'];
        $userId = auth()->id();

        // 获取与登录用户有已接受好友关系（状态 = 2）的用户 ID
        $excludedFriendIds = Friendlist::where(function ($friendQuery) use ($userId) {
                $friendQuery->where('user_id', $userId)
                            ->orWhere('friend_id', $userId);
            })
            ->where('status', 2) // 仅接受的好友关系
            ->get()
            ->flatMap(function ($friendship) use ($userId) {
                return [$friendship->user_id, $friendship->friend_id]; // 包括两个 ID
            })
            ->unique()
            ->toArray();

        // 将登录用户的 ID 添加到排除列表
        $excludedFriendIds[] = $userId;

        // 获取被排除用户的姓名
        $excludedUserNames = User::whereIn('id', $excludedFriendIds)
                                 ->pluck('name')
                                 ->toArray();

        // 如果需要，执行不区分大小写的搜索
        $friend = User::whereNotIn('id', $excludedFriendIds) // Exclude friends and yourself
            ->where(function ($q) use ($query) {
                $q->where('referral_link', '=', $query) // Exact match
                  ->orWhere('name', '=', $query);  // Exact match
            })
            ->first();


        if (!$friend) {
            Log::warning('搜索时未找到好友', ['query' => $query]);
            return response()->json([
                'status' => 'error',
                'message' => '未找到好友',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'name' => $friend->name,
            'realname' => $friend->realname,
            'referral_link' => $friend->referral_link,
        ]);
    }

    public function getFriends(Request $request)
    {
        $userId = auth()->id();
    
        try {
            // 获取涉及登录用户的已接受好友关系（状态 = 2）
            $friendships = Friendlist::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('friend_id', $userId);
            })
            ->where('status', 2) // 仅接受的好友关系
            ->with(['user', 'friend']) // 加载相关的用户和好友详情
            ->get();
    
            // 映射好友关系以显示好友的详情
            $friends = $friendships->map(function ($friendship) use ($userId) {
                // 确定好友（不是登录用户）
                $friend = ($friendship->user_id === $userId) 
                    ? $friendship->friend // 如果登录用户是请求者，获取好友
                    : $friendship->user;  // 否则，获取请求者
    
                $lastLogin = \DB::table('last_online')
                    ->where('user_id', $friend->id)
                    ->latest('updated_at') // Sort by `updated_at` in descending order
                    ->value('updated_at'); // Retrieve the latest `updated_at` value

                Carbon::setLocale('zh');
                
                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friend->id,
                    'name' => '',
                    'avatar' => $friend->avatar,
                    'referral_link' => $friend->referral_link,
                    'status' => $friendship->status,
                    'realname' => $friend->realname ?? 'N/A',
                    'age' => $friend->age ?? 'N/A',
                    'current_status' =>  $friend->status,
                    'last_active' => $lastLogin 
                        ? Carbon::parse($lastLogin)->diffForHumans()
                        : '未知', // 如果没有登录记录，则显示 "未知"
                    'qr_link' => $friend->qr_link,
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'friends' => $friends,
                'count' => $friends->count(), // 添加好友数量
            ]);
        } catch (\Exception $e) {
            Log::error('获取好友时出错', [
                'user_id' => $userId,
                'error_message' => $e->getMessage(),
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => '获取好友时发生错误。',
            ], 500);
        }
    }

    public function updateFriendStatus(Request $request, $id)
    {
        $user = $this->getCurrentUser($request);

        $validated = $request->validate([
            'status' => 'required|in:2,3', // 2: 接受, 3: 拒绝
        ]);

        $friendship = Friendlist::find($id);

        if (!$friendship || ($friendship->user_id != auth()->id() && $friendship->friend_id != auth()->id())) {
            Log::warning('未找到好友关系或未经授权的访问', [
                'user_id' => auth()->id(),
                'friendship_id' => $id,
            ]);
            return response()->json(['status' => 'error', 'message' => '未找到好友关系'], 404);
        }

        $friendship->status = $validated['status'];
        $friendship->save();
        
        event(new NewFriendRequestEvent($id));

        if ($validated['status'] == 2) {
            DB::beginTransaction();
            try {
                // 确定好友的用户 ID
                $friendUserId = ($friendship->user_id == auth()->id()) ? $friendship->friend_id : $friendship->user_id;
        
                // 检查是否已存在对话
                $existingConversation = Conversation::where(function ($query) use ($friendUserId) {
                    $query->where('name', auth()->id())
                          ->where('target', $friendUserId);
                })->orWhere(function ($query) use ($friendUserId) {
                    $query->where('name', $friendUserId)
                          ->where('target', auth()->id());
                })->first();
        
                if ($existingConversation) {
                    // 检查对话中是否已有消息
                    $existingMessage = Message::where('conversation_id', $existingConversation->id)->exists();
        
                    if ($existingMessage) {
                        // 跳过创建对话和消息
                        return response()->json([
                            'status' => 'success',
                            'message' => '对话和初始消息已存在。',
                        ]);
                    } else {
                        // 尚无消息，创建第一条消息
                        $message = Message::create([
                            'conversation_id' => $existingConversation->id,
                            'user_id' => auth()->id(),
                            'message' => '你好，开始我们的聊天啦', // "你好，开始我们的聊天啦"
                            'created_at' => now(),
                        ]);
                        
                        DB::commit();

                        return response()->json([
                            'status' => 'success',
                            'message' => '为现有对话创建了初始消息。',
                        ]);
                    }
                }
        
                // 如果不存在任何对话，则创建新的对话
                $conversation = Conversation::create([
                    'name' => $friendUserId,
                    'target' => auth()->id(),
                ]);
        
                // 创建初始消息
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => auth()->id(),
                    'message' => '你好，开始我们的聊天啦', // "你好，开始我们的聊天啦"
                    'created_at' => now(),
                ]);
        
                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => '好友关系状态已更新并创建了对话。',
                    'conversation_id' => $conversation->id, // 包含对话 ID
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('创建对话和消息时出错', [
                    'error_message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
        
                return response()->json([
                    'status' => 'error',
                    'message' => '好友关系已更新，但无法创建对话。',
                ], 500);
            }
        }
        
        if ($validated['status'] == 3) {

            return response()->json([
                'status' => 'success',
                'message' => '好友请求已拒绝。',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => '好友关系状态已更新并创建了对话。',
            'conversation_id' => $conversation->id, // 包含对话 ID
        ]);
    }
    
    public function getIncomingRequests(Request $request)
    {

        $userId = auth()->id();
    
        try {
            // Get pending friend requests where the user is the receiver
            $incomingRequests = Friendlist::where('friend_id', $userId)
                ->where('status', 1) // Only pending requests
                ->with('user') // Load the requester (user_id) relationship
                ->get();
    
            // Map incoming requests to return relevant details
            $incoming = $incomingRequests->map(function ($friendship) {
                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friendship->user->id,
                    'name' => $friendship->user->name,
                    'referral_link' => $friendship->user->referral_link,
                    'realname' => $friendship->user->realname,
                ];
            });
    
            return response()->json([
                'status' => 'success',
                'incoming_requests' => $incoming,
                'count' => $incoming->count(), // Add the count of incoming requests
            ]);
        } catch (\Exception $e) {
            Log::error('Error in /incoming_requests', [
                'user_id' => $userId,
                'error_message' => $e->getMessage(),
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching incoming requests.',
            ], 500);
        }
    }
    
    public function viewFriendList(Request $request)
    {
        $token = $request->get('impersonation_token');

        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");

            // Authenticate as the impersonated user
            Auth::onceUsingId($impersonationData['robot_id']);
            $userId = auth()->id();
        } else {
            $userId = auth()->id();
        }

        // Fetch accepted friendships
        $friends = Friendlist::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('friend_id', $userId);
            })
            ->where('status', 2) // Only accepted friends
            ->get();
    
        // Extract friend IDs to fetch nicknames in bulk
        $friendIds = $friends->map(function ($friendship) use ($userId) {
            return ($friendship->user_id === $userId) ? $friendship->friend_id : $friendship->user_id;
        })->unique()->toArray();
    
        // Fetch all nicknames set by the current user for these friends
        $remarks = Remark::where('user_id', $userId)
                        ->whereIn('target_id', $friendIds)
                        ->get()
                        ->keyBy('target_id'); // Key by target_id for easy access
    
        // Fetch wallet information for all friends in bulk
        $wallets = Wallet::whereIn('user_id', $friendIds)->get()->keyBy('user_id');
    
        // Fetch all user details in bulk
        $users = User::whereIn('id', $friendIds)->get()->keyBy('id');
    
        // Map over friendships to prepare the detailed friend list
        $friendDetails = $friends->map(function ($friendship) use ($userId, $users, $wallets, $remarks) {
            // Determine the friend's user ID
            $friendId = ($friendship->user_id === $userId) ? $friendship->friend_id : $friendship->user_id;
    
            // Get the friend's user data
            $friend = $users->get($friendId);
    
            // Get the friend's wallet data
            $wallet = $wallets->get($friendId);
    
            // Get the nickname from remarks, if exists
            $nickname = $remarks->has($friendId) ? $remarks->get($friendId)->nickname : null;
    
            // If the friend exists, return the detailed data
            if ($friend) {
                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'realname' => $friend->realname ?? 'N/A',
                    'age' => $friend->age ?? 'N/A',
                    'referral_link' => $friend->referral_link,
                    'avatar_url' => $friend->avatar 
                        ? url('storage/' . $friend->avatar) 
                        : '/default-avatar.png', // Default placeholder if no avatar
    
                    'wallet_balance' => $wallet->amount ?? 0, // Balance
                    'wallet_freeze' => $wallet->freeze ?? 0, // Frozen balance
                    'status' => $friendship->status,
    
                    'nickname' => $nickname ?? $friend->realname ?? $friend->name, // Use nickname if available
                ];
            }
    
            return null;
        })->filter();
    
        // Check if the request expects JSON (AJAX)
        if ($request->ajax()) {
            return response()->json(['friends' => $friendDetails]);
        }
    
        // Otherwise, render the HTML view
        return view('pages.app.robot.friendlist', ['friends' => $friendDetails]);
    }
}