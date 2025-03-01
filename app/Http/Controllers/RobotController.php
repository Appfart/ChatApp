<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Events\NewFriendRequestEvent;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Friendlist;
use App\Models\GrpChatSetting;
use App\Models\Grpchat;
use App\Models\Grpmessage;
use App\Models\Message;
use App\Models\Remark;
use App\Models\User;

use App\Notifications\UserTagged;


class RobotController extends Controller
{
    private function getCurrentUser(Request $request)
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

                /*Log::channel('robot')->info('用户已模拟为机器人', [
                    'support_id' => $supportId,
                    'robot_id' => $robotId,
                    'token' => $token,
                ]);*/

                return $user;
            }
        }

        // **未提供模拟令牌**
        $user = auth()->user();
        return $user;
    }

    public function dashboard(Request $request)
    {
        $user = $this->getCurrentUser($request);
    
        // Fetch last login
        $lastLogin = \DB::table('last_online')
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->value('updated_at');
        $user->last_login = $lastLogin;
    
        // Fetch conversations
        $conversations = Conversation::where(function ($query) use ($user) {
                $query->where('name', $user->id)
                      ->orWhere('target', $user->id);
            })
            ->with(['latestMessage', 'user', 'targetUser'])
            ->get();
    
        // Fetch group chats
        $grpchats = Grpchat::whereJsonContains('members', (string) $user->id)
            ->with(['latestMessage', 'messages'])
            ->get();
    
        // Get all nicknames for this user
        $remarks = \App\Models\Remark::where('user_id', $user->id)->get()->keyBy('target_id');
    
        // ✅ Ensure `$targetUser` is never null
        $targetUser = $conversations->first() ? 
            ($conversations->first()->target == $user->id ? $conversations->first()->user : $conversations->first()->targetUser) 
            : null;
    
        // Transform conversations
        $transformedConversations = $conversations->map(function ($conversation) use ($user, $remarks) {
            $targetId = $conversation->target === $user->id ? $conversation->name : $conversation->target;
            $nickname = $remarks->get($targetId)->nickname ?? 'none';
            
            // Calculate unread count based on last read timestamp
            $lastRead = $conversation->read_timestamps[$user->id] ?? null;
            $unreadCount = $lastRead
                ? $conversation->messages()->where('created_at', '>', $lastRead)->count()
                : $conversation->messages()->count();
            return [
                'type'           => 'conversation',
                'id'             => $conversation->id,
                'data'           => $conversation,
                'latest_message' => $conversation->latestMessage,
                'unread_count'   => $unreadCount, // Use computed value here
                'nickname'       => $nickname,
            ];
        });
        
        // Transform group chats
        $transformedGrpchats = $grpchats->map(function ($grpchat) use ($user) {
            // Calculate unread count based on last read timestamp
            $lastRead = $grpchat->read_timestamps[$user->id] ?? null;
            $unreadCount = $lastRead
                ? $grpchat->messages()->where('created_at', '>', $lastRead)->count()
                : $grpchat->messages()->count();
        
            return [
                'type'           => 'grpchat',
                'id'             => $grpchat->id,
                'data'           => $grpchat,
                'latest_message' => $grpchat->latestMessage,
                'unread_count'   => $unreadCount, // Use computed value here
            ];
        });

    
        // Merge and sort chats
        $sortedChats = $transformedConversations->merge($transformedGrpchats)
            ->sortByDesc(fn ($chat) => $chat['latest_message']->created_at ?? Carbon::parse('1970-01-01'))
            ->values();
    
        return view('pages.app.robot.dashboard', compact('user', 'sortedChats', 'targetUser'));
    }
    
    public function getSortedChats(Request $request)
    {

        try {
            // Step 1: Retrieve Current User
            $user = $this->getCurrentUser($request);

            // Step 2: Retrieve Impersonation Token (if any)
            $token = $request->get('impersonation_token');
            if ($token) {
            }
    
            // Step 3: Fetch Personal Conversations
            $conversations = Conversation::where(function ($query) use ($user) {
                    $query->where('name', $user->id)
                          ->orWhere('target', $user->id);
                })
                ->with(['latestMessage', 'messages'])
                ->get();

            // Step 4: Fetch Group Chats
            $grpchats = Grpchat::whereJsonContains('members', (string) $user->id)
                ->with(['latestMessage', 'messages'])
                ->get();

            // Step 5: Transform Conversations and Group Chats
            $transformedConversations = $conversations->map(function ($conversation) use ($user) {
                $lastRead = $conversation->read_timestamps[$user->id] ?? null;
                $unreadCount = $lastRead
                    ? $conversation->messages()->where('created_at', '>', $lastRead)->count()
                    : $conversation->messages()->count();
                
                $latestMessage = $conversation->latestMessage;
                
                // Resolve the content of the latest message
                if ($latestMessage) {
                    if ($latestMessage->status == 0) {
                        $latestMessageContent = '信息已经撤回'; // "Message has been withdrawn"
                    } else {
                        $latestMessageContent = $latestMessage->message 
                            ?? ($latestMessage->image_url ? '图片信息' 
                                : ($latestMessage->audio_url ? '音频信息' 
                                    : ($latestMessage->doc_url ? '文档信息' 
                                        : ($latestMessage->video_url ? '视频信息' : '有新消息'))));
                    }
                } else {
                    $latestMessageContent = null; // No latest message
                }

    
                return [
                    'type' => 'conversation',
                    'id' => $conversation->id,
                    'data' => $conversation,
                    'latest_message' => $latestMessage,
                    'latest_message_content' => $latestMessageContent,
                    'unread_count' => $unreadCount,
                ];
            });
    
            $transformedGrpchats = $grpchats->map(function ($grpchat) use ($user) {
                $lastRead = $grpchat->read_timestamps[$user->id] ?? null;
                $unreadCount = $lastRead
                    ? $grpchat->messages()->where('created_at', '>', $lastRead)->count()
                    : $grpchat->messages()->count();
    
                $latestMessage = $grpchat->latestMessage;
                
                if ($latestMessage) {
                    if ($latestMessage->status == 0) {
                        $latestMessageContent = '信息已经撤回'; // "Message has been withdrawn"
                    } else {
                        $latestMessageContent = $latestMessage->message 
                            ?? ($latestMessage->image_url ? '图片信息' 
                                : ($latestMessage->audio_url ? '音频信息' 
                                    : ($latestMessage->doc_url ? '文档信息' 
                                        : ($latestMessage->video_url ? '视频信息' : '有新消息'))));
                    }
                } else {
                    $latestMessageContent = null; // No latest message
                }

    
                return [
                    'type' => 'grpchat',
                    'id' => $grpchat->id,
                    'data' => $grpchat,
                    'latest_message' => $latestMessage,
                    'latest_message_content' => $latestMessageContent,
                    'unread_count' => $unreadCount,
                ];
            });
    
            // Step 6: Merge and Sort Chats
            $sortedChats = $transformedConversations->merge($transformedGrpchats)->sortByDesc(function ($chat) {
                return $chat['latest_message'] ? $chat['latest_message']->created_at : Carbon::parse('1970-01-01');
            })->values();

            // Step 7: Return JSON Response
            return response()->json(['sorted_chats' => $sortedChats]);
        } catch (\Exception $e) {
            // Log errors with essential information
            Log::error('Error in getSortedChats.', [
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    
    public function getMessages(Request $request, $chatId)
    {
        $user = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');
        $isGroupChat = filter_var($request->get('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
        // Pagination parameters
        $limit = $request->get('limit', 30); // Default to 30 if not provided
        $offset = $request->get('offset', 0); // Default to 0 if not provided
    
        if ($isGroupChat) {
            $grpchat = Grpchat::where('id', $chatId)
                ->whereJsonContains('members', (string) $user->id)
                ->firstOrFail();
    
            // Fetch group settings
            $settings = $grpchat->settings;
    
            // Determine if the user is muted
            $isMuted = false;
            if ($settings->mute_chat == 1) {
                if ($grpchat->owner_id !== $user->id && !in_array($user->id, $grpchat->admins ?? [])) {
                    $isMuted = true;
                }
            }
    
            // Fetch all members of the group chat
            $memberIds = $grpchat->members;
    
            // Fetch all remarks for the current user with group members
            $remarks = \App\Models\Remark::where('user_id', $user->id)
                ->whereIn('target_id', $memberIds)
                ->get()
                ->keyBy('target_id');
            
            // 更新此用户的已读时间戳
            $readTimestamps = $grpchat->read_timestamps ?? [];
            $readTimestamps[$user->id] = now()->toDateTimeString();
            $grpchat->read_timestamps = $readTimestamps;
            $grpchat->save();

            $messages = $grpchat->messages()
                ->with(['user', 'replyTo.user', 'taggedUsers'])
                ->where(function ($query) use ($user) {
                    $query->where('status', '!=', 0)
                          ->orWhere(function ($q) use ($user) {
                              $q->where('status', '=', 0)
                                ->where('user_id', '=', $user->id);
                          });
                })
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->sortBy('created_at')
                ->values()
                ->map(function ($msg) use ($user, $remarks) {
                    $remark = $remarks->get($msg->user->id);
                
                    // Retrieve remark for replyTo user, if exists
                    $replyToRemark = $msg->replyTo ? $remarks->get($msg->replyTo->user->id) : null;
                
                    // Determine the fallback message content
                    $fallbackMessage = '未知信息'; // Default fallback if all are null
                    if ($msg->status != 0) {
                        $fallbackMessage = $msg->message 
                            ?? ($msg->image_url ? '图片信息'
                                : ($msg->audio_url ? '音频信息'
                                    : ($msg->doc_url ? '文档信息'
                                        : ($msg->video_url ? '视频信息' : $fallbackMessage))));
                    }
                
                    // Determine reply_to_message fallback
                    $replyToMessage = $msg->replyTo 
                        ? ($msg->replyTo->message 
                            ?? ($msg->replyTo->image_url ? '图片信息'
                                : ($msg->replyTo->audio_url ? '音频信息'
                                    : ($msg->replyTo->doc_url ? '文档信息'
                                        : ($msg->replyTo->video_url ? '视频信息' : '未知信息')))))
                        : null;
                
                    return [
                        'id' => $msg->id,
                        'grpchat_id' => $msg->grpchat_id,
                        'user_id' => $msg->user_id,
                        'user' => [
                            'id' => $msg->user->id,
                            'name' => $msg->user->realname,
                            'nickname' => $remark ? $remark->nickname : "none",
                            'avatar' => $msg->user->avatar,
                            'age' => $msg->user->age,
                            'referral_link' => $msg->user->referral_link,
                        ],
                        'message' => $msg->status == 0 ? '【信息已经撤回】' : $fallbackMessage,
                        'image_url' => $msg->status == 0 ? null : $msg->image_url,
                        'doc_url' => $msg->status == 0 ? null : $msg->doc_url,
                        'video_url' => $msg->status == 0 ? null : $msg->video_url,
                        'audio_url' => $msg->status == 0 ? null : $msg->audio_url,
                        'reply_to_id' => $msg->reply_to_id,
                        'reply_to_user_name' => $msg->replyTo 
                            ? ($replyToRemark 
                                ? $replyToRemark->nickname 
                                : $msg->replyTo->user->realname)
                            : null,
                        'reply_to_message' => $replyToMessage,
                        'tagged_users' => $msg->taggedUsers->map(function ($u) {
                            return ['id' => $u->id, 'name' => $u->name];
                        }),
                        'status' => $msg->status,
                        'created_at' => $msg->created_at->toDateTimeString(),
                    ];
                })
                ->toArray();
            
            $readTimestamps = $grpchat->read_timestamps;
            
            // Prepare chat_info for group chat
            $chatInfo = [
                'type' => 'grpchat',
                'avatar' => $grpchat->avatar,
                'chatname' => $grpchat->chatname,
                'member_count' => count($grpchat->members),
                'owner_name' => optional($grpchat->owner)->realname,
            ];
            
            $memberIds = array_map('intval', $grpchat->members);
            $users = User::whereIn('id', $memberIds)->get(['id', 'name', 'realname', 'avatar']);
            
            // Transform users into the desired structure
            $usersArray = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname ?? 'none',
                    'avatar' => $user->avatar,
                ];
            })->toArray();
            
            // Return the response with 'users' included
            return response()->json([
                'messages' => $messages,
                'is_muted' => $isMuted,
                'chat_info' => $chatInfo,
                'read_timestamps' => $readTimestamps,
                'users' => $users,
                'grpchat_id' => $grpchat->id,
            ]);
            
        } else {
            $conversation = Conversation::where('id', $chatId)
                ->where(function ($query) use ($user) {
                    $query->where('name', $user->id)
                          ->orWhere('target', $user->id);
                })
                ->firstOrFail();
    
            // Determine the other user's ID
            $otherUserId = $conversation->name == $user->id ? $conversation->target : $conversation->name;
    
            // Fetch the other user's details
            $otherUser = \App\Models\User::findOrFail($otherUserId);
            
            $lastLogin = \DB::table('last_online')
            ->where('user_id', $otherUser->id)
            ->latest('updated_at')
            ->value('updated_at');
    
            // Check for nickname in remarks
            $remark = \App\Models\Remark::where('user_id', $user->id)
                ->where('target_id', $otherUserId)
                ->first();
    
            $messages = $conversation->messages()
                ->with(['user', 'replyTo.user', 'taggedUsers'])
                ->where(function($query) use ($user) {
                    $query->where('status', '!=', 0)
                          ->orWhere(function($q) use ($user) {
                              $q->where('status', '=', 0)
                                ->where('user_id', '=', $user->id);
                          });
                })
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->sortBy('created_at')
                ->values()
                ->map(function ($msg) use ($user) {
                    $remark = \App\Models\Remark::where('user_id', $user->id)
                        ->where('target_id', $msg->user->id)
                        ->first();
                    
                    $replyToRemark = $msg->replyTo 
                        ? \App\Models\Remark::where('user_id', $user->id)
                            ->where('target_id', $msg->replyTo->user->id)
                            ->first()
                        : null;
            
                    // Determine the fallback message content
                    $fallbackMessage = '未知信息'; // Default fallback for null message
                    if ($msg->status != 0) {
                        $fallbackMessage = $msg->message 
                            ?? ($msg->image_url ? '图片信息'
                                : ($msg->audio_url ? '音频信息'
                                    : ($msg->doc_url ? '文档信息'
                                        : ($msg->video_url ? '视频信息' : $fallbackMessage))));
                    }
            
                    // Determine reply_to_message fallback
                    $replyToMessage = $msg->replyTo 
                        ? ($msg->replyTo->message 
                            ?? ($msg->replyTo->image_url ? '图片信息'
                                : ($msg->replyTo->audio_url ? '音频信息'
                                    : ($msg->replyTo->doc_url ? '文档信息'
                                        : ($msg->replyTo->video_url ? '视频信息' : '未知信息')))))
                        : null;
            
                    return [
                        'id' => $msg->id,
                        'conversation_id' => $msg->conversation_id,
                        'user_id' => $msg->user_id,
                        'user' => [
                            'id' => $msg->user->id,
                            'name' => $msg->user->realname,
                            'nickname' => $remark ? $remark->nickname : "none",
                            'avatar' => $msg->user->avatar,
                            'age' => $msg->user->age,
                            'referral_link' => $msg->user->referral_link,
                        ],
                        'message' => $msg->status == 0 ? '【信息已经撤回】' : $fallbackMessage,
                        'image_url' => $msg->status == 0 ? null : $msg->image_url,
                        'doc_url' => $msg->status == 0 ? null : $msg->doc_url,
                        'audio_url' => $msg->status == 0 ? null : $msg->audio_url,
                        'video_url' => $msg->status == 0 ? null : $msg->video_url,
                        'reply_to_id' => $msg->reply_to_id,
                        'reply_to_user_name' => $msg->replyTo 
                            ? ($replyToRemark 
                                ? $replyToRemark->nickname 
                                : $msg->replyTo->user->realname)
                            : null,
                        'reply_to_message' => $replyToMessage,
                        'tagged_users' => $msg->taggedUsers->map(function ($u) {
                            return ['id' => $u->id, 'name' => $u->name];
                        }),
                        'status' => $msg->status,
                        'created_at' => $msg->created_at->toDateTimeString(),
                    ];
                })
                ->toArray();

    
            // Update read timestamps
            $readTimestamps = $conversation->read_timestamps ?? [];
            $readTimestamps[$user->id] = now()->toDateTimeString();
            $conversation->read_timestamps = $readTimestamps;
            $conversation->save();
            
            $readTimestamp = isset($conversation->read_timestamps[$otherUser->id]) 
            ? $conversation->read_timestamps[$otherUser->id] 
            : null;
    
            // Prepare chat_info for personal conversation
            $chatInfo = [
                'type' => 'conversation',
                'avatar' => $otherUser->avatar,
                'id' => $otherUser->id,
                'realname' => $otherUser->realname,
                'age' => $otherUser->age,
                'nickname' => $remark ? $remark->nickname : $otherUser->realname,
                'online' => $otherUser->is_online,
                'last_online' => $lastLogin,
                'conversation_id' => $conversation->id,
            ];
    
            return response()->json([
                'messages' => $messages,
                'chat_info' => $chatInfo,
                'read_timestamp' => $readTimestamp,
            ]);
        }
    }
    
    public function sendMessage(Request $request)
    {
        $user = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');
    
        $request->merge([
            'is_group_chat' => filter_var($request->get('is_group_chat'), FILTER_VALIDATE_BOOLEAN),
        ]);
    
        try {
            $validatedData = $request->validate([
                'message' => 'required|string',
                'chat_id' => 'required|integer',
                'is_group_chat' => 'required|boolean',
                'reply_to_id' => 'nullable|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'token' => $token,
            ]);
            return response()->json(['error' => '验证失败', 'details' => $e->errors()], 422);
        }
    
        // Function to extract tagged usernames from the message
        $taggedUsernames = $this->extractUsernames($validatedData['message']);
    
        if ($validatedData['is_group_chat']) {
            // Handling group chat messages
            $grpchat = Grpchat::findOrFail($validatedData['chat_id']);
            if (!in_array((string)$user->id, $grpchat->members)) {
                return response()->json(['error' => '未经授权的操作。'], 403);
            }
    
            $settings = $grpchat->settings;
    
            // Check if the group chat is muted
            if ($settings->mute_chat == 1) {
                // Check if the user is owner or admin
                $isOwnerOrAdmin = $grpchat->owner_id == $user->id || in_array($user->id, $grpchat->admins ?? []);
    
                if (!$isOwnerOrAdmin) {
                    return response()->json(['error' => '此群聊已被管理员禁用，您无法发送消息。'], 403);
                }
            }
    
            // Check if the user is muted in the group chat
            if (in_array($user->id, $settings->mute_members ?? [])) {
                return response()->json(['error' => '您在此群聊中已被禁言，无法发送消息。'], 403);
            }
    
            // Create the group message
            $message = new Grpmessage();
            $message->grpchat_id = $grpchat->id;
            $message->user_id = $user->id;
            $message->message = $validatedData['message'];
    
            if (!empty($validatedData['reply_to_id'])) {
                $message->reply_to_id = $validatedData['reply_to_id'];
            }
    
            $message->save();
    
            // Handle tagged users
            if (!empty($taggedUsernames)) {
                $taggedUsers = User::whereIn('name', $taggedUsernames)
                                   ->whereIn('id', $grpchat->members)
                                   ->where('id', '!=', $user->id)
                                   ->get();
    
                if ($taggedUsers->isNotEmpty()) {
                    $message->taggedUsers()->attach($taggedUsers->pluck('id')->toArray());
    
                    // Notify tagged users
                    foreach ($taggedUsers as $taggedUser) {
                        $taggedUser->notify(new UserTagged($message));
                    }
                }
    
                if (strpos($message->message, '@everyone') !== false || strpos($message->message, '@all') !== false) {
                    foreach ($grpchat->members as $memberId) {
                        if ($memberId != $user->id) {
                            $member = User::find($memberId);
                            if ($member) {
                                $member->notify(new UserTagged($message));
                            }
                        }
                    }
                }
            }
    
            // Fetch all remarks for recipients in this group chat towards the sender
            $groupMembers = $grpchat->members; // Assuming 'members' is an array of user IDs
            $remarks = Remark::whereIn('user_id', $groupMembers)
                             ->where('target_id', $user->id)
                             ->get()
                             ->keyBy('user_id'); // Key by recipient's user ID for easy access
    
            // Prepare the base message data
            $baseBroadcastData = [
                'type' => 'grpchat',
                'grpchat_id' => $message->grpchat_id,
                'message' => $message->message 
                    ?? ($message->image_url ? '图片信息'
                        : ($message->audio_url ? '音频信息'
                            : ($message->doc_url ? '文档信息'
                                : ($message->video_url ? '视频信息' : '未知信息')))),
                'user_id' => $message->user_id,
                'sender_name' => $user->realname ?? 'Unknown',
                'sender_avatar' => $user->avatar ?? 'default-avatar.png',
                'audio_url' => $message->audio_url ?? null,
                'doc_url' => $message->doc_url ?? null,
                'image_url' => $message->image_url ?? null,
                'created_at' => $message->created_at->toIso8601String(),
                'id' => $message->id,
                'tagged_users' => $message->taggedUsers->map(function ($u) {
                    return ['id' => $u->id, 'name' => $u->realname];
                }),
                'reply_to_id' => $message->reply_to_id,
                'reply_to_user_name' => $message->replyTo->user->realname ?? 'Unknown',
                'reply_to_message' => $message->replyTo
                    ? ($message->replyTo->message 
                        ?? ($message->replyTo->image_url ? '图片信息'
                            : ($message->replyTo->audio_url ? '音频信息'
                                : ($message->replyTo->doc_url ? '文档信息'
                                    : ($message->replyTo->video_url ? '视频信息' : '未知信息')))))
                    : null,
            ];


            // Broadcast the message to each group member with their respective nickname
            foreach ($groupMembers as $memberId) {
                if ($memberId != $user->id) { 
                    
                    $remark = $remarks->get($memberId);
                    $nickname = $remark ? $remark->nickname : ($user->realname ?? 'Unknown');
    
                    // Merge the nickname into the broadcast data
                    $broadcastData = array_merge($baseBroadcastData, [
                        'sender_nickname' => $nickname,
                    ]);
    
                    // Broadcast the event
                    broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                }
            }
    
            return response()->json([
                'message' => $message->message 
                    ?? ($message->image_url ? '图片信息'
                        : ($message->audio_url ? '音频信息'
                            : ($message->doc_url ? '文档信息'
                                : ($message->video_url ? '视频信息' : '未知信息')))),
                'created_at' => $message->created_at->toDateTimeString(),
                'id' => $message->id,
                'sender_name' => $user->realname ?? 'Unknown',
                'sender_avatar' => $user->avatar ?? 'default-avatar.png',
                'reply_to_id' => $message->reply_to_id,
                'reply_to_user_name' => $message->replyTo 
                    ? (isset($remarks[$message->replyTo->user->id]) 
                        ? $remarks[$message->replyTo->user->id]->nickname 
                        : ($message->replyTo->user->realname ?? 'Unknown'))
                    : null,
                'reply_to_message' => $message->replyTo
                    ? ($message->replyTo->message 
                        ?? ($message->replyTo->image_url ? '图片信息'
                            : ($message->replyTo->audio_url ? '音频信息'
                                : ($message->replyTo->doc_url ? '文档信息'
                                    : ($message->replyTo->video_url ? '视频信息' : '未知信息')))))
                    : null,
                'tagged_users' => $message->taggedUsers->map(function ($u) {
                    return ['id' => $u->id, 'name' => $u->realname];
                }),
            ]);


        } else {
            // 处理个人对话消息
            $conversation = Conversation::where('id', $validatedData['chat_id'])
                ->where(function ($query) use ($user) {
                    $query->where('name', $user->id)
                          ->orWhere('target', $user->id);
                })
                ->firstOrFail();
            
            // ** Check friendlist status **
            $recipientId = $conversation->getOtherUserId($user->id);
            $friendship = Friendlist::where(function ($query) use ($user, $recipientId) {
                $query->where('user_id', $user->id)->where('friend_id', $recipientId);
            })->orWhere(function ($query) use ($user, $recipientId) {
                $query->where('user_id', $recipientId)->where('friend_id', $user->id);
            })->first();
    
            if (!$friendship || $friendship->status != 2) {
                Log::warning('发送消息被阻止，好友关系未建立', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'friendlist_status' => $friendship->status ?? 'Not Found',
                ]);
                return response()->json(['error' => '您无法向此用户发送消息，好友关系未建立。'], 403);
            }
            
            // **Remove the recipient user from the 'remove' array**
            if (!empty($conversation->remove)) {
                // Filter out the recipient user's removal entry
                $updatedRemove = array_values(array_filter($conversation->remove, function ($item) use ($recipientId) {
                    return $item['id'] != $recipientId;
                }));
    
                // Update the 'remove' attribute only if changes were made
                if (count($updatedRemove) !== count($conversation->remove)) {
                    $conversation->remove = $updatedRemove;
                    $conversation->save();

                }
            }

            $message = new Message();
            $message->conversation_id = $conversation->id;
            $message->user_id = $user->id;
            $message->message = $validatedData['message'];
            
            if (!empty($validatedData['reply_to_id'])) {
                $message->reply_to_id = $validatedData['reply_to_id'];
            }
            
            $message->save();

            // 获取接收者 ID
            $recipientId = $conversation->getOtherUserId($user->id);
            $me = $user->id;
            
            $replyToMessage = $message->replyTo;
            $replyToUserName = null;
            
            // Check if a nickname exists for the reply-to user
            if ($replyToMessage) {
                $remark = \App\Models\Remark::where('user_id', $user->id)
                    ->where('target_id', $replyToMessage->user->id)
                    ->first();
            
                $replyToUserName = $remark ? $remark->nickname : $replyToMessage->user->realname;
            }
            
            $readTimestamp = isset($conversation->read_timestamps[$recipientId]) 
            ? $conversation->read_timestamps[$recipientId] 
            : null;
            
            // 广播事件
            broadcast(new MessageSent($message, $recipientId, $me));
            
            Log::info('Broadcasting message', [
                'message' => $message,
                'recipient_id' => $recipientId,
                'sender' => $me,
            ]);
            
            return response()->json([
                'message' => $message->message 
                    ?? ($message->image_url ? '图片信息'
                        : ($message->audio_url ? '音频信息'
                            : ($message->doc_url ? '文档信息'
                                : ($message->video_url ? '视频信息' : '未知信息')))),
                'created_at' => $message->created_at->toDateTimeString(),
                'id' => $message->id,
                'sender_name' => $message->user->realname ?? 'Unknown',
                'sender_avatar' => $message->user->avatar ?? 'default-avatar.png',
                'reply_to_id' => $message->reply_to_id,
                'reply_to_user_name' => $message->replyTo 
                    ? ($remark 
                        ? $remark->nickname 
                        : ($message->replyTo->user->realname ?? 'Unknown'))
                    : null,
                'reply_to_message' => $message->replyTo 
                    ? ($message->replyTo->message 
                        ?? ($message->replyTo->image_url ? '图片信息'
                            : ($message->replyTo->audio_url ? '音频信息'
                                : ($message->replyTo->doc_url ? '文档信息'
                                    : ($message->replyTo->video_url ? '视频信息' : '未知信息')))))
                    : null,
                'tagged_users' => $message->taggedUsers->map(function ($u) {
                    return ['id' => $u->id, 'name' => $u->realname];
                }),
                'read_timestamp' => $readTimestamp,
            ]);
        }
    }

    private function extractUsernames($message)
    {
        // 使用正则查找所有 @username 模式
        preg_match_all('/@(\w+)/', $message, $matches);

        // $matches[1] 包含用户名
        return $matches[1];
    }
    
    public function sendFile(Request $request)
    {
        Log::channel('robot')->info('调用 sendFile 函数', [
            'request_data' => $request->all(),
        ]);
    
        $user = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');
    
        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");
        }
        
        $isGroupChat = filter_var($request->input('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
        $chatId = $request->input('chat_id');

        try {
            try {
                $validatedData = $request->validate([
                    'file' => 'required|file|max:100240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,mp4',
                    'chat_id' => 'required|integer',
                    'is_group_chat' => 'required|boolean',
                ]);
            } catch (\Illuminate\Validation\ValidationException $ve) {
                Log::channel('robot')->error('【文件上传验证失败】', [
                    'errors'       => $ve->errors(),
                    'request_data' => $request->all(),
                ]);
                throw $ve;
            }
    
            $isGroupChat = filter_var($request->input('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
            $chatId = $request->input('chat_id');

            $file = $request->file('file');
            if (!$file) {
                Log::channel('robot')->error('【file 对象为空】', [
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'error', 'message' => '未检测到文件'], 422);
            }
    
            $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            if ($isGroupChat) {
                $grpchat = Grpchat::where('id', $chatId)
                    ->whereJsonContains('members', (string) $user->id)
                    ->firstOrFail();
    
                $settings = $grpchat->settings;
    
                if ($settings->mute_chat == 1) {
                    $isOwnerOrAdmin = $grpchat->owner_id == $user->id || in_array($user->id, $grpchat->admins ?? []);
                    if (!$isOwnerOrAdmin) {
                        return response()->json(['error' => '此群聊已对所有成员静音。'], 403);
                    }
                }
    
                if (in_array($user->id, $settings->mute_members ?? [])) {
                    return response()->json(['error' => '您在此群聊中已被禁言。'], 403);
                }
    
                $storagePath = 'images/grpchat/' . $chatId;
                $path = $file->storeAs($storagePath, $filename, 'public');
    
                $grpMessage = new Grpmessage();
                $grpMessage->grpchat_id = $grpchat->id;
                $grpMessage->user_id = $user->id;
    
                $latestMessageContent = '有新消息';
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $grpMessage->image_url = '/' . $path;
                } elseif (str_starts_with($file->getMimeType(), 'video/')) {
                    $grpMessage->video_url = '/' . $path;
                } else {
                    $grpMessage->doc_url = '/' . $path;
                }
    
                $grpMessage->save();

                foreach ($grpchat->members as $memberId) {
                    if ($memberId != $user->id) {
                        $broadcastData = [
                            'type'               => 'grpchat',
                            'grpchat_id'         => $grpMessage->grpchat_id,
                            'message'            => $grpMessage->message 
                                ?? ($grpMessage->image_url ? '图片信息' 
                                    : ($grpMessage->audio_url ? '音频信息' 
                                        : ($grpMessage->doc_url ? '文档信息' 
                                            : ($grpMessage->video_url ? '视频信息' : $latestMessageContent)))),
                            'user_id'            => $grpMessage->user_id,
                            'sender_name'        => $user->realname ?? '未知',
                            'sender_avatar'      => $user->avatar ?? 'default-avatar.png',
                            'audio_url'          => $grpMessage->audio_url ?? null,
                            'doc_url'            => $grpMessage->doc_url ?? null,
                            'image_url'          => $grpMessage->image_url ?? null,
                            'video_url'          => $grpMessage->video_url ?? null,
                            'created_at'         => $grpMessage->created_at->toIso8601String(),
                            'id'                 => $grpMessage->id,
                            'tagged_users'       => [],
                            'reply_to_id'        => $grpMessage->reply_to_id ?? null,
                            'reply_to_user_name' => optional(optional($grpMessage->replyTo)->user)->realname,
                            'reply_to_message'   => optional($grpMessage->replyTo)->message,
                        ];

                        broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                    }
                }
    
                return response()->json([
                    'status' => 'success',
                    'image_url' => $grpMessage->image_url,
                    'doc_url' => $grpMessage->doc_url,
                    'video_url' => $grpMessage->video_url,
                    'created_at' => $grpMessage->created_at->toIso8601String(),
                    'id' => $grpMessage->id,
                    'sender_name' => $user->realname ?? '未知',
                    'isgroupchat' => 1,
                ]);
    
            } else {
                $conversation = Conversation::where('id', $chatId)
                    ->where(function ($query) use ($user) {
                        $query->where('name', $user->id)
                              ->orWhere('target', $user->id);
                    })
                    ->firstOrFail();
    
                $storagePath = 'images/conversations/' . $chatId;
                $path = $file->storeAs($storagePath, $filename, 'public');
                $message = new Message();
                $message->conversation_id = $conversation->id;
                $message->user_id = $user->id;
    
                $latestMessageContent = '有新消息';
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $message->image_url = '/' . $path;
                } elseif (str_starts_with($file->getMimeType(), 'video/')) {
                    $message->video_url = '/' . $path;
                } else {
                    $message->doc_url = '/' . $path;
                }

    
                $message->save();
                $recipientId = $conversation->getOtherUserId($user->id);
                $me = $message->user_id;
                
                // Fetch Remark for the current user and the recipient
                $remark = \App\Models\Remark::where('user_id', $recipientId)
                    ->where('target_id', $user->id)
                    ->first();
                
                // Determine the sender name
                $senderName = $remark ? $remark->nickname : ($user->realname ?? '未知');
                
                $broadcastData = [
                    'type'            => 'conversation',
                    'conversation_id' => $message->conversation_id,
                    'message'         => $message->message 
                        ?? ($message->image_url ? '图片信息' 
                            : ($message->doc_url ? '文档信息' 
                                : ($message->video_url ? '视频信息'
                                    : $latestMessageContent))),
                    'image_url'       => $message->image_url ?? null,
                    'doc_url'         => $message->doc_url ?? null,
                    'video_url'       => $message->video_url ?? null,
                    'user_id'         => $message->user_id,
                    'sender_name'     => $user->realname ?? '未知',
                    'created_at'      => $message->created_at->toIso8601String(),
                    'id'              => $message->id,
                    'tagged_users'    => [],
                ];

                broadcast(new MessageSent($message, $recipientId, $me))->toOthers();
                
                // *** ADD THIS RETURN: ***
                return response()->json([
                    'status' => 'success',
                    'image_url' => $message->image_url,
                    'doc_url' => $message->doc_url,
                    'video_url' => $message->video_url,
                    'created_at' => $message->created_at->toIso8601String(),
                    'id' => $message->id,
                    'sender_name' => $senderName,
                ]);
            }
    
        } catch (\Exception $e) {
            Log::channel('robot')->error('【文件上传失败，异常捕获】', [
                'error'        => $e->getMessage(),
                'request_data' => $request->all(),
                'trace'        => $e->getTraceAsString(),
            ]);
            return response()->json(['status' => 'error', 'message' => '文件上传失败。'], 500);
        }
    }
    
    public function sendGroupFile(Request $request)
    {
        return $this->sendFile($request);
    }
    
    public function sendAudio(Request $request)
    {
        $user  = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');
    
        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");
            Log::channel('robot')->info('使用了模拟令牌', [
                'token'              => $token,
                'impersonation_data' => $impersonationData,
            ]);
        }
    
        $isGroupChat = filter_var($request->input('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
        $chatId      = $request->input('chat_id');

        try {
            try {
                $validatedData = $request->validate([
                    'audio'         => 'required|file|mimes:webm,ogg,wav,aac|max:10240',
                    'chat_id'       => 'required|integer',
                    'is_group_chat' => 'required|boolean',
                ]);
            } catch (\Illuminate\Validation\ValidationException $ve) {
                Log::channel('robot')->error('【音频上传验证失败】', [
                    'errors'       => $ve->errors(),
                    'request_data' => $request->all(),
                ]);
                throw $ve;
            }
    
            $isGroupChat = filter_var($request->input('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
            $chatId      = $request->input('chat_id');

    
            $file = $request->file('audio');
            if (!$file) {
                Log::channel('robot')->error('【audio 文件对象为空】', [
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'error', 'message' => '未检测到音频文件'], 422);
            }
    
            $filename   = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

            if ($isGroupChat) {
                $grpchat = Grpchat::where('id', $chatId)
                    ->whereJsonContains('members', (string) $user->id)
                    ->firstOrFail();
    
                $settings = $grpchat->settings;
    
                if ($settings->mute_chat == 1) {
                    $isOwnerOrAdmin = $grpchat->owner_id == $user->id || in_array($user->id, $grpchat->admins ?? []);
                    if (!$isOwnerOrAdmin) {
                        return response()->json(['error' => '此群聊已对所有成员静音。'], 403);
                    }
                }
    
                if (in_array($user->id, $settings->mute_members ?? [])) {
                    return response()->json(['error' => '您在此群聊中已被禁言。'], 403);
                }
    
                $storagePath = 'audio/grpchat/' . $chatId;
                $path        = $file->storeAs($storagePath, $filename, 'public');
                
                $grpMessage = new Grpmessage();
                $grpMessage->grpchat_id = $grpchat->id;
                $grpMessage->user_id    = $user->id;
                $grpMessage->message    = ''; // 或者 '[语音]'
                $grpMessage->audio_url  = '/' . $path;
                $grpMessage->save();
    
                foreach ($grpchat->members as $memberId) {
                    if ($memberId != $user->id) {
                        $broadcastData = [
                            'type'         => 'grpchat',
                            'grpchat_id'   => $grpMessage->grpchat_id,
                            'message'      => $grpMessage->message 
                                ?? ($grpMessage->audio_url ? '音频信息' : null),
                            'audio_url'    => $grpMessage->audio_url,
                            'user_id'      => $grpMessage->user_id,
                            'sender_name'  => $user->realname ?? '未知',
                            'sender_avatar'=> $user->avatar ?? 'default-avatar.png',
                            'created_at'   => $grpMessage->created_at->toIso8601String(),
                        ];

                        broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                    }
                }
    
                return response()->json([
                    'status'     => 'success',
                    'audio_url'  => $grpMessage->audio_url,
                    'created_at' => $grpMessage->created_at->toDateTimeString(),
                ]);
    
            } else {
                $conversation = Conversation::where('id', $chatId)
                    ->where(function ($query) use ($user) {
                        $query->where('name', $user->id)
                              ->orWhere('target', $user->id);
                    })
                    ->firstOrFail();
    
                $storagePath = 'audio/conversations/' . $chatId;
                $path        = $file->storeAs($storagePath, $filename, 'public');
                
                $message = new Message();
                $message->conversation_id = $conversation->id;
                $message->user_id         = $user->id;
                $message->message         = ''; // 或者 '[语音]'
                $message->audio_url       = '/' . $path;
                $message->save();
    
                $recipientId = $conversation->getOtherUserId($user->id);
                $me = $message->user_id;
                broadcast(new MessageSent($message, $recipientId, $me))->toOthers();
    
                return response()->json([
                    'status'     => 'success',
                    'audio_url'  => $message->audio_url,
                    'created_at' => $message->created_at->toDateTimeString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('robot')->error('音频上传失败', [
                'error'        => $e->getMessage(),
                'request_data' => $request->all(),
                'token'        => $token,
                'trace'        => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => '音频上传失败。'
            ], 500);
        }
    }

    public function searchUsers(Request $request)
    {
        $user = $this->getCurrentUser($request);

        $search = $request->get('query', ''); // Default to empty string if no query provided
        $grpchatId = $request->get('grpchat_id');

        $query = User::query();
    
        if ($grpchatId) {
            // Fetch group chat members
            $grpchat = Grpchat::find($grpchatId);
    
            if (!$grpchat) {
                Log::warning('Group chat not found or unauthorized access', [
                    'grpchat_id' => $grpchatId,
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => '未授权的操作。'], 403);
            }
    
            if (!in_array((string)$user->id, $grpchat->members)) {
                Log::warning('User not authorized to access this group chat', [
                    'grpchat_id' => $grpchatId,
                    'user_id' => $user->id,
                    'group_members' => $grpchat->members,
                ]);
                return response()->json(['error' => '未授权的操作。'], 403);
            }
            
            $query->whereIn('id', $grpchat->members);
        }
    
        // Exclude the current user
        $query->where('id', '!=', $user->id);
    
        // Apply search filter if query is provided
        if (!empty($search)) {
            $query->where('realname', 'LIKE', '%' . $search . '%'); // Match against realname instead of name
        }
    
        // Fetch users with a limit
        $users = $query->limit(10)->get(['id', 'name', 'avatar', 'realname']);

        // Prepend base URL to avatar paths
        $users->transform(function ($user) {
            $user->avatar = $user->avatar
                ? url('storage/' . $user->avatar)
                : 'https://via.placeholder.com/30'; // Default placeholder
            return $user;
        });
    
        return response()->json(['users' => $users]);
    }

    public function getIncomingRequests(Request $request)
    {
        $user = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');

        try {
            $incomingRequests = Friendlist::where('friend_id', $user->id)
                ->where('status', 1)
                ->with('user')
                ->get();

            $incoming = $incomingRequests->map(function ($friendship) {
                $wallet = DB::table('wallets')
                    ->where('user_id', $friendship->user->id)
                    ->select('amount', 'freeze')
                    ->first();

                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friendship->user->id,
                    'name' => $friendship->user->name,
                    'realname' => $friendship->user->realname,
                    'referral_link' => $friendship->user->referral_link,
                    'age' => $friendship->user->age,
                    'created_at' => $friendship->user->created_at->format('Y-m-d'),
                    'wallet_balance' => $wallet ? $wallet->amount : 0,
                    'wallet_freeze' => $wallet ? $wallet->freeze : 0,
                ];
            });

            return response()->json([
                'status' => 'success',
                'incoming_requests' => $incoming,
            ]);
        } catch (\Exception $e) {
            Log::channel('robot')->error('获取入站请求时出错', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            return response()->json(['status' => 'error', 'message' => '获取请求时出错。'], 500);
        }
    }
    
    public function updateFriendStatus(Request $request, $id)
    {
        $user = $this->getCurrentUser($request);
        $token = $request->get('impersonation_token');

        try {
            $validated = $request->validate([
                'status' => 'required|in:2,3', // 2: 接受, 3: 拒绝
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('robot')->error('验证失败', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'token' => $token,
            ]);
            return response()->json(['status' => 'error', 'message' => '输入无效。'], 422);
        }

        $friendship = Friendlist::find($id);

        if (!$friendship || ($friendship->friend_id != $user->id && $friendship->user_id != $user->id)) {
            Log::channel('robot')->warning('未找到友谊或用户不在友谊中', [
                'friendship_id' => $id,
                'user_id' => $user->id,
                'token' => $token,
            ]);
            return response()->json(['status' => 'error', 'message' => '未找到友谊。'], 404);
        }

        $friendship->status = $validated['status'];
        $friendship->save();
        event(new NewFriendRequestEvent($friendship->friend_id));

        if ($validated['status'] == 2) { // 接受
            DB::transaction(function () use ($friendship, $user) {
                $friendUserId = ($friendship->user_id == $user->id) ? $friendship->friend_id : $friendship->user_id;
        
                $existingConversation = Conversation::where(function ($query) use ($friendUserId, $user) {
                    $query->where('name', $friendUserId)
                          ->where('target', $user->id);
                })->orWhere(function ($query) use ($friendUserId, $user) {
                    $query->where('name', $user->id)
                          ->where('target', $friendUserId);
                })->first();
        
                if ($existingConversation) {
                    $existingMessage = Message::where('conversation_id', $existingConversation->id)->exists();
        
                    if ($existingMessage) {
                        return;
                    } else {
                        Message::create([
                            'conversation_id' => $existingConversation->id,
                            'user_id' => $user->id,
                            'message' => '你好，开始我们的聊天啦', // "Hello, let's start our chat!"
                        ]);

                        return;
                    }
                }
        
                $conversation = Conversation::create([
                    'name' => $user->id,
                    'target' => $friendUserId,
                ]);
        
                Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'message' => '你好，开始我们的聊天啦', // "Hello, let's start our chat!"
                ]);
            });
        }

        return response()->json(['status' => 'success', 'message' => '好友请求已处理。']);
    }
    
    public function quitGrpChat(Request $request, $id)
    {
        try {
            $user = $this->getCurrentUser($request);
            $token = $request->get('impersonation_token');

            $grpchat = Grpchat::findOrFail($id);
            
            // 检查是否允许退出
            $settings = $grpchat->settings;
            if ($settings->block_quit == 1) {
                return response()->json(['error' => '退出此群聊已被禁用。'], 403);
            }

            if ((string)$grpchat->owner === (string)$user->id) {
                Log::warning('所有者尝试退出群组', ['user_id' => $user->id, 'grpchat_id' => $id]);
                return response()->json(['status' => 'error', 'message' => '所有者无法退出群组。'], 403);
            }

            // 从成员中移除用户
            $membersBefore = array_map('strval', $grpchat->members);
            $updatedMembers = array_filter($membersBefore, function ($member) use ($user) {
                return $member !== (string)$user->id;
            });
            $grpchat->members = array_values($updatedMembers);

            // 添加到已退出成员
            $quitMembers = $grpchat->quitmembers ?? [];
            $quitMembers[] = [
                'id' => $user->id,
                'timestamp' => now()->toIso8601String()
            ];
            $grpchat->quitmembers = $quitMembers;

            $grpchat->save();

            return response()->json(['status' => 'success', 'message' => '您已退出群组。']);
        } catch (\Exception $e) {
            Log::channel('robot')->error('退出群组时出错', [
                'error' => $e->getMessage(),
                'grpchat_id' => $id,
                'user_id' => isset($user) ? $user->id : '未知',
                'token' => $request->get('impersonation_token'),
            ]);
            return response()->json(['status' => 'error', 'message' => '退出群组失败。'], 500);
        }
    }
    
    public function revertImpersonation(Request $request)
    {
        $token = $request->get('impersonation_token');

        if ($token && session()->has("impersonation_{$token}")) {
            $impersonationData = session("impersonation_{$token}");

            if (isset($impersonationData['is_self']) && $impersonationData['is_self']) {
                session()->forget("impersonation_{$token}");
                return redirect()->route('lobby.robotchat')->with('success', '您已还原到您的账户。');
            } else {
                session()->forget("impersonation_{$token}");
                return redirect()->route('lobby.robotchat')->with('success', '您已还原到您的账户。');
            }
        }

        Log::channel('robot')->warning('还原模拟失败：无效或缺失的令牌', [
            'token' => $token,
            'user_id' => auth()->id(),
        ]);

        abort(403, '您未模拟任何用户。');
    }
    
    public function recallMessage(Request $request)
    {
        $user = $this->getCurrentUser($request);
    
        // Define validation rules
        $rules = [
            'message_id' => 'required|integer',
            'is_group_chat' => 'required|boolean',
        ];
    
        // Validate the request
        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
            Log::channel('api')->warning("Validation failed for recallMessage", [
                'errors' => $e->errors(),
                'payload' => $request->all(),
                'timestamp' => now()->toIso8601String(),
            ]);
            return response()->json(['error' => '验证失败', 'details' => $e->errors()], 422);
        }
    
        // Extract validated data
        $messageId = $validated['message_id'];
        $isGroupChat = filter_var($validated['is_group_chat'], FILTER_VALIDATE_BOOLEAN);
        $authUserId = Auth::id();
    
        // Check if the user is authenticated
        if (!$authUserId) {
            Log::channel('api')->warning('Unauthorized recallMessage attempt.', [
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
            return response()->json(['error' => '未授权操作'], 403);
        }
    
        // Determine the appropriate message model based on chat type
        $messageModel = $isGroupChat ? Grpmessage::class : Message::class;
    
        try {
            // Retrieve the message with related user and reply information
            $message = $messageModel::with(['user', 'replyTo.user'])
                ->where('id', $messageId)
                ->first();
    
            // If message not found, return 404
            if (!$message) {
                Log::channel('api')->warning('Message not found for recallMessage.', [
                    'message_id' => $messageId,
                    'is_group_chat' => $isGroupChat,
                    'user_id' => $authUserId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '消息未找到'], 404);
            }
    
            // Initialize variables for group chat authorization
            $isOwnerOrAdmin = false;
            $grpchat = null;
    
            if ($isGroupChat) {
                // Retrieve the group chat details
                $grpchat = $message->grpchat; // Ensure Grpmessage model has a 'grpchat' relationship
    
                if ($grpchat) {
                    // Check if the user is the owner or an admin of the group chat
                    $isOwnerOrAdmin = ($grpchat->owner_id == $user->id) || in_array($user->id, $grpchat->admins ?? []);
                } else {
                    // If group chat details are missing, log and return error
                    Log::channel('api')->warning('Group chat details missing for recallMessage.', [
                        'grpchat_id' => $message->grpchat_id ?? null,
                        'message_id' => $messageId,
                        'user_id' => $authUserId,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => '群聊信息缺失'], 500);
                }
            }
    
            // Check if the user is authorized: owner/admin or message sender
            if (!$isOwnerOrAdmin && $message->user_id !== $authUserId) {
                Log::channel('api')->warning('Unauthorized recallMessage attempt by user.', [
                    'message_id' => $messageId,
                    'is_group_chat' => $isGroupChat,
                    'user_id' => $authUserId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '您无权撤回此消息'], 403);
            }
    
            // Check if the message is already recalled
            if ($message->status === 0) {
                return response()->json(['message' => '消息已被撤回'], 200);
            }
    
            // Update the message content and status to indicate recall
            $message->message = '【信息已经撤回】';
            $message->status = 0;
            $message->save();
    
            // Reload the updated message with relationships
            $updatedMessage = $messageModel::with(['user', 'replyTo.user'])->find($messageId);
    
            if (!$updatedMessage) {
                Log::channel('api')->warning('Updated message not found after recall.', [
                    'message_id' => $messageId,
                    'is_group_chat' => $isGroupChat,
                    'user_id' => $authUserId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '无法获取已更新的消息'], 500);
            }
    
            // Prepare a log-friendly version of data (not the event param)
            $broadcastData = [
                'id'                  => $updatedMessage->id,
                'message'             => $updatedMessage->message,
                'created_at'          => $updatedMessage->created_at->toIso8601String(),
                'status'              => $updatedMessage->status,
                'type'                => $isGroupChat ? 'grecall' : 'crecall',
                'sender_name'         => $updatedMessage->user->realname ?? '未知',
                'sender_avatar'       => $updatedMessage->user->avatar ?? 'default-avatar.png',
                'reply_to_id'         => $updatedMessage->reply_to_id,
                'reply_to_user_name'  => $updatedMessage->replyTo->user->realname ?? null,
                'reply_to_message'    => $updatedMessage->replyTo->message ?? null,
                'tagged_users'        => [],
                'user_id' => $authUserId,
            ];
    
            // Broadcast the recall event (pass the model to fix the type error)
            try {
                if ($isGroupChat) {
                    foreach ($grpchat->members as $memberId) {
                        broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                    }
                } else {
                    // One-on-one recall
                    $recipientId = $updatedMessage->conversation->getOtherUserId($authUserId);
                    $me = $authUserId;
    
                    broadcast(new MessageSent($updatedMessage, $recipientId, $me, 'crecall'))->toOthers();
    
                }
            } catch (\Exception $e) {
                Log::channel('api')->error('Error broadcasting recall event.', [
                    'error'       => $e->getMessage(),
                    'message_id'  => $updatedMessage->id,
                    'timestamp'   => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '广播撤回事件时发生错误'], 500);
            }
        
            // Return a successful response
            return response()->json([
                'message_id'     => $updatedMessage->id,
                'is_group_chat'  => $isGroupChat,
                'status'         => '信息已撤回',
                'timestamp'      => now()->toIso8601String(),
            ], 200);
            
        } catch (\Exception $e) {
            // Log any unexpected errors
            Log::channel('api')->error('Error in recallMessage.', [
                'error'         => $e->getMessage(),
                'message_id'    => $messageId,
                'is_group_chat' => $isGroupChat,
                'user_id'       => $authUserId,
                'timestamp'     => now()->toIso8601String(),
            ]);
            return response()->json(['error' => '撤回消息时发生错误'], 500);
        }
    }

    public function removeConversation(Request $request, $grpchatId)
    {

        // Step 1: Authenticate User
        $user = $this->getCurrentUser($request);
    
        // Step 2: Validate if the request is for a group chat
        $isGroupChat = filter_var($request->get('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
    
        if ($isGroupChat) {
            // Handle Group Chat Removal
    
            // Since we don't allow removal via this endpoint, reject the request
            Log::warning('Attempted to remove group chat via removeConversation endpoint', [
                'grpchat_id' => $grpchatId,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => '此接口不允许移除群聊。'], 403); // "This endpoint does not allow removing group chats."
        } else {
            // Handle Personal Chat Removal
            $conversationId = $grpchatId; // Assume $grpchatId is used for personal chats too
            $conversation = Conversation::find($conversationId);
    
            if (!$conversation) {
                Log::warning('Personal conversation not found', [
                    'conversation_id' => $conversationId,
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => '未找到个人聊天记录。'], 404);
            }
    
            // Ensure the user is part of the conversation
            if ($conversation->name != $user->id && $conversation->target != $user->id) {
                Log::warning('Unauthorized personal conversation removal attempt', [
                    'conversation_id' => $conversationId,
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => '您无权删除此聊天。'], 403);
            }
            
            // Check if the `remove` property exists
            $removeLog = $conversation->remove ?? []; // Retrieve current value or initialize as an empty array
    
            // Record the removal action
            $removeLog[] = [
                'id' => $user->id,
                'timestamp' => now()->toIso8601String(),
            ];
    
            $conversation->remove = $removeLog; // Assign the updated array back to the attribute
            $conversation->save();
    
            return response()->json([
                'success' => true,
                'message' => '个人聊天已成功移除。',
                'remove_log' => $conversation->remove,
            ]);
        }
    }
    
    public function update(Request $request)
    {
        $robotId = $request->input('robot_id');
        $user = User::find($robotId);
    
        if (!$user) {
            return redirect()->back()->withErrors(['error' => '无法更新机器人信息：未找到用户。']);
        }
    
        try {
            $validatedData = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'regex:/^[a-zA-Z0-9]+$/',
                    'max:255',
                    Rule::unique('users', 'name')->ignore($user->id),
                ],
                'realname' => 'nullable|string|max:255',
                'security_pin' => 'nullable|min:4',
                'password' => 'nullable|string|min:4',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'name.required' => '名称是必需的。',
                'name.string' => '名称必须是一个字符串。',
                'name.regex' => '名称只能包含字母和数字。',
                'name.max' => '名称的最大长度为255个字符。',
                'name.unique' => '名称已被使用，请选择另一个名称。',
                'realname.string' => '真实名称必须是一个字符串。',
                'realname.max' => '真实名称的最大长度为255个字符。',
                'security_pin.min' => '安全PIN码的最小长度为4个字符。',
                'password.string' => '密码必须是一个字符串。',
                'password.min' => '密码的最小长度为4个字符。',
                'avatar.image' => '头像必须是图像文件。',
                'avatar.mimes' => '头像文件类型必须是jpeg, png, jpg, 或gif。',
                'avatar.max' => '头像文件大小不能超过2048KB。',
            ]);
        } catch (ValidationException $e) {
            // Log validation errors
            Log::error('Validation failed during robot update', [
                'robot_id' => $robotId,
                'errors' => $e->errors(),
                'input' => $request->all(),
                'user_id' => auth()->id(),
            ]);
    
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    
        // Update the robot's fields
        $user->name = $validatedData['name'];
        $user->realname = $validatedData['realname'];
        if (!empty($validatedData['security_pin'])) {
            $user->security_pin = $validatedData['security_pin'];
        }
        if (!empty($validatedData['password'])) {
            $user->password = bcrypt($validatedData['password']);
        }
    
        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $avatarPath;
        }
    
        // Save the changes
        $user->save();
        return redirect()->back()->with('success', '机器人信息已成功更新。');
    }
    
    public function remarkUpdate(Request $request)
    {
        // Get the current user
        $user = $this->getCurrentUser($request);
    
        // Validate the request
        $validatedData = $request->validate([
            'target_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value == $user->id) {
                        $fail('您不能为自己设置备注。'); // Chinese error message
                    }
                },
            ],
            'nickname' => 'required|string|max:255',
        ]);
    
        // Save the remark
        try {
            \DB::transaction(function () use ($user, $validatedData) {
                \App\Models\Remark::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'target_id' => $validatedData['target_id'],
                    ],
                    [
                        'nickname' => $validatedData['nickname'],
                    ]
                );
            });
    
            return response()->json(['status' => 'success', 'message' => '备注保存成功。']);
        } catch (\Exception $e) {
            \Log::error('Failed to save remark', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => '保存备注失败。'], 500);
        }
    }
    
    public function categoryStore(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
    
        // Determine the current user using the private function
        $user = $this->getCurrentUser($request);
    
        // Create the category
        $category = Category::create([
            'name' => $request->name,
            'user_id' => $user->id, // Use the determined user ID
            'member_id' => null,
            'status' => 1,
        ]);
    
        // Return response
        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }
    
    public function addMember(Request $request, $id)
    {
        $user = $this->getCurrentUser($request);
    
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
    
        $category = Category::where('id', $id)
            ->where('user_id', $user->id) // Ensure the category belongs to the current user
            ->firstOrFail();
    
        $members = $category->member_id ? json_decode($category->member_id, true) : [];
    
        if (!in_array($request->user_id, $members)) {
            $members[] = $request->user_id;
            $category->member_id = json_encode($members);
            $category->save();
        }
    
        return response()->json([
            'message' => 'Member added to category successfully',
        ]);
    }
    
    public function fetchCategories(Request $request)
    {
        try {
            $user = $this->getCurrentUser($request);
    
            // Fetch categories where the current user is the owner
            $categories = Category::where('user_id', $user->id)->get();
    
            // Ensure member_id is always an array
            foreach ($categories as $category) {
                $category->member_id = $category->member_id ? json_decode($category->member_id, true) : [];
            }
    
            return response()->json($categories);
        } catch (\Exception $e) {
            \Log::error('Error fetching categories', [
                'error' => $e->getMessage(),
                'user' => $user ?? 'N/A',
            ]);
    
            return response()->json(['error' => 'Unable to fetch categories'], 500);
        }
    }
    
    public function viewCategories(Request $request)
    {
        try {
            $user = $this->getCurrentUser($request);
            Log::info('Fetching categories for user', ['user_id' => $user->id]);
    
            // Fetch categories for the current user
            $categories = Category::where('user_id', $user->id)->get();
            Log::info('Categories fetched', ['count' => $categories->count()]);
    
            // Decode member_id for each category
            $categories->each(function ($category) {
                $category->member_id = $category->member_id ? json_decode($category->member_id, true) : [];
            });
    
            // Collect all unique member IDs across all categories
            $allMemberIds = $categories->pluck('member_id')
                ->flatten()
                ->unique()
                ->filter()
                ->toArray();
            Log::info('Collected member IDs', ['member_ids' => $allMemberIds]);
    
            // Fetch user details for all member IDs
            $users = User::whereIn('id', $allMemberIds)->get(['id', 'realname', 'avatar']);
            Log::info('User details fetched', ['users_count' => $users->count()]);
    
            // Map users by their ID for quick access
            $usersMap = $users->keyBy('id');
    
            // Attach user details to each category
            $categoriesWithMembers = $categories->map(function ($category) use ($usersMap) {
                $memberIds = $category->member_id ? $category->member_id : [];
                $members = array_map(function ($id) use ($usersMap) {
                    return $usersMap->has($id) ? $usersMap->get($id) : null;
                }, $memberIds);
    
                // Filter out any null values in case some user IDs were not found
                $members = array_filter($members);
    
                Log::debug('Mapped members for category', ['category_id' => $category->id, 'members_count' => count($members)]);
    
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'members' => array_values($members), // Reindex the array
                ];
            });
    
            Log::info('Categories with members prepared', ['categories_count' => $categoriesWithMembers->count()]);
    
            return response()->json([
                'categories' => $categoriesWithMembers,
                'message' => 'Categories fetched successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching categories for view', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json(['error' => 'Unable to fetch categories'], 500);
        }
    }
    
    public function myqrcode(Request $request)
    {

        $user = $this->getCurrentUser($request);
    
        if (!$user) {
            Log::warning('Unauthorized access attempt', ['request' => $request->all()]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    
        $qrLink = $user->qr_link;
    
        if (!$qrLink || !file_exists(public_path($qrLink))) {
            Log::error('QR Code not found', ['user_id' => $user->id, 'qr_link' => $qrLink]);
            return response()->json(['error' => 'QR Code not found'], 404);
        }
    
        $qrCodeUrl = asset("{$qrLink}");
    

        return response()->json([
            'success' => true,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

}
