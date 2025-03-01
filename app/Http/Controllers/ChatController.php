<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Grpchat;
use App\Models\Grpmessage;
use Illuminate\Support\Facades\Auth;

use App\Events\MessageSent;
use App\Events\GroupMessageSent;
use App\Notifications\UserTagged;
use App\Models\Friendlist;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

use Carbon\Carbon;

class ChatController extends Controller
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger->channel('chat');
    }

    public function index()
    {
        $authUserId = Auth::id();
    
        $conversations = Conversation::where(function ($query) use ($authUserId) {
                $query->where('conversations.name', $authUserId) // Explicitly specify the table
                      ->orWhere('conversations.target', $authUserId); // Explicitly specify the table
            })
            ->leftJoin('users as target_users', function ($join) use ($authUserId) {
                $join->on('conversations.target', '=', 'target_users.id')
                     ->where('conversations.name', $authUserId);
            })
            ->leftJoin('users as name_users', function ($join) use ($authUserId) {
                $join->on('conversations.name', '=', 'name_users.id')
                     ->where('conversations.target', $authUserId);
            })
            ->select(
                'conversations.*',
                DB::raw('COALESCE(target_users.name, name_users.name) as other_user_name')
            )
            ->get();
    
        return view('pages.app.chat', compact('conversations'))->with('title', 'Chat');
    }

    // Send a message from web interface
    public function sendMessage(Request $request)
    {
        $this->logger->info("===>Send Message Request:", $request->all());

        // Validate the request
        $validated = $request->validate([
            'message' => 'required',
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        try {
            // Create the message
            $message = Message::create([
                'conversation_id' => $validated['conversation_id'],
                'user_id' => Auth::id(),
                'message' => $validated['message'],
            ]);
            
            return response()->json($message);

        } catch (\Exception $e) {
            $this->logger->error('Error saving message:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    //Flutter
    public function getMessages(Request $request, $chat_id)
    {
        $authUserId = Auth::id();
        $isGroupChat = filter_var($request->query('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
    
        try {
            if ($isGroupChat) {
                // Fetch group chat
                $groupChat = Grpchat::where('id', $chat_id)
                    ->whereJsonContains('members', (string)$authUserId)
                    ->first();
    
                if (!$groupChat) {
                    Log::channel('api')->warning('Unauthorized access attempt to group chat.', [
                        'grpchat_id' => $chat_id,
                        'auth_user_id' => $authUserId,
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                $readTimestamps = $groupChat->read_timestamps ?? [];
                $readTimestamps[$authUserId] = now()->toDateTimeString();
                $groupChat->read_timestamps = $readTimestamps;
                $groupChat->save();
    
                // Fetch group chat messages with user and replyTo relationships
               $messages = Grpmessage::with(['user', 'replyTo.user'])
                    ->where('grpchat_id', $chat_id)
                    ->where(function($query) use ($authUserId) {
                        $query->where('status', '!=', 0)
                              ->orWhere(function($q) use ($authUserId) {
                                  $q->where('status', '=', 0)
                                    ->where('user_id', '=', $authUserId);
                              });
                    })
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($message) {
                        // Prepare reply_to_message data if applicable
                        $replyToMessageData = null;
                        if ($message->replyTo) {
                            $replyToMessageData = [
                                'id' => $message->replyTo->id,
                                'sender_name' => $message->replyTo->user->realname ?? 'Unknown',
                                'sender_avatar' => $message->replyTo->user->avatar ?? 'default-avatar.png',
                                'message' => $message->replyTo->message,
                                'created_at' => $message->replyTo->created_at->toIso8601String(),
                            ];
                        }
    
                        return [
                            'id' => $message->id,
                            'grpchat_id' => $message->grpchat_id,
                            'user_id' => $message->user_id,
                            'sender_name' => $message->user->realname ?? 'Unknown',
                            'sender_avatar' => $message->user->avatar ?? 'default-avatar.png',
                            'message' => $message->status == 0 
                                ? '信息已经撤回' 
                                : ($message->message 
                                    ?? ($message->image_url ? '图片信息' 
                                        : ($message->audio_url ? '音频信息' 
                                            : ($message->doc_url ? '文档信息' 
                                                : ($message->video_url ? '视频信息' 
                                                    : null))))),
                            'image_url' => $message->status == 0 ? null : $message->image_url,
                            'doc_url' => $message->status == 0 ? null : $message->doc_url,
                            'audio_url' => $message->status == 0 ? null : $message->audio_url,
                            'video_url' => $message->status == 0 ? null : $message->video_url,
                            'status' => $message->status,
                            'created_at' => $message->created_at->toIso8601String(),
                            'reply_to_id' => $message->reply_to_id,
                            'reply_to_message' => $replyToMessageData,
                        ];

                    });
            } else {
                // Fetch normal conversation
                $conversation = Conversation::where('id', $chat_id)
                    ->where(function ($query) use ($authUserId) {
                        $query->where('name', $authUserId)
                              ->orWhere('target', $authUserId);
                    })
                    ->first();
    
                if (!$conversation) {
                    Log::channel('api')->warning('Unauthorized access attempt to conversation.', [
                        'conversation_id' => $chat_id,
                        'auth_user_id' => $authUserId,
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                // Update read timestamp for this user
                $readTimestamps = $conversation->read_timestamps ?? [];
                $readTimestamps[$authUserId] = now()->toDateTimeString();
                $conversation->read_timestamps = $readTimestamps;
                $conversation->save();
    
                // Fetch conversation messages with user and replyTo relationships
                $messages = Message::with(['user', 'replyTo.user'])
                    ->where('conversation_id', $chat_id)
                    ->where(function($query) use ($authUserId) {
                        $query->where('status', '!=', 0)
                              ->orWhere(function($q) use ($authUserId) {
                                  $q->where('status', '=', 0)
                                    ->where('user_id', '=', $authUserId);
                              });
                    })
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($message) {
                        // Prepare reply_to_message data if applicable
                        $replyToMessageData = null;
                        if ($message->replyTo) {
                            $replyToMessageData = [
                                'id' => $message->replyTo->id,
                                'sender_name' => $message->replyTo->user->realname ?? 'Unknown',
                                'sender_avatar' => $message->replyTo->user->avatar ?? 'default-avatar.png',
                                'message' => $message->replyTo->message,
                                'created_at' => $message->replyTo->created_at->toIso8601String(),
                            ];
                        }
    
                        return [
                            'id' => $message->id,
                            'conversation_id' => $message->conversation_id,
                            'user_id' => $message->user_id,
                            'sender_name' => $message->user->realname ?? 'Unknown',
                            'sender_avatar' => $message->user->avatar ?? 'default-avatar.png',
                            'message' => $message->status == 0 
                                ? '信息已经撤回' 
                                : ($message->message 
                                    ?? ($message->image_url ? '图片信息' 
                                        : ($message->audio_url ? '音频信息' 
                                            : ($message->doc_url ? '文档信息' 
                                                : ($message->video_url ? '视频信息' 
                                                    : null))))),
                            'image_url' => $message->status == 0 ? null : $message->image_url,
                            'doc_url' => $message->status == 0 ? null : $message->doc_url,
                            'audio_url' => $message->status == 0 ? null : $message->audio_url,
                            'video_url' => $message->status == 0 ? null : $message->video_url,
                            'created_at' => $message->created_at->toIso8601String(),
                            'reply_to_id' => $message->reply_to_id,
                            'reply_to_message' => $replyToMessageData,
                            'status' => $message->status,
                        ];

                    });
            }
    
            return response()->json(['messages' => $messages]);
    
        } catch (\Exception $e) {
            Log::channel('api')->error('Error fetching messages.', [
                'chat_id' => $chat_id,
                'auth_user_id' => $authUserId,
                'is_group_chat' => $isGroupChat,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json(['error' => 'Failed to fetch messages'], 500);
        }
    }
    
    public function getMessagesb(Request $request, $chat_id)
    {
        $authUserId = Auth::id();
        $isGroupChat = filter_var($request->query('is_group_chat'), FILTER_VALIDATE_BOOLEAN);
        // Set a default limit if not provided
        $limit = $request->query('limit', 20);
        // Optionally, get a timestamp to load messages before that time
        $before = $request->query('before');
        
        // Log the received parameters
    Log::debug('Lazy load parameters', [
        'chat_id' => $chat_id,
        'is_group_chat' => $isGroupChat,
        'limit' => $limit,
        'before' => $before,
        'auth_user_id' => $authUserId,
    ]);
    
        try {
            if ($isGroupChat) {
                $groupChat = Grpchat::where('id', $chat_id)
                    ->whereJsonContains('members', (string)$authUserId)
                    ->first();
    
                if (!$groupChat) {
                    Log::channel('api')->warning('Unauthorized access attempt to group chat.', [
                        'grpchat_id' => $chat_id,
                        'auth_user_id' => $authUserId,
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                // Update read timestamps
                $readTimestamps = $groupChat->read_timestamps ?? [];
                $readTimestamps[$authUserId] = now()->toDateTimeString();
                $groupChat->read_timestamps = $readTimestamps;
                $groupChat->save();
    
                // Build the query for messages
                $query = Grpmessage::with(['user', 'replyTo.user'])
                    ->where('grpchat_id', $chat_id)
                    ->where(function($query) use ($authUserId) {
                        $query->where('status', '!=', 0)
                              ->orWhere(function($q) use ($authUserId) {
                                  $q->where('status', '=', 0)
                                    ->where('user_id', '=', $authUserId);
                              });
                    });
    
                // If a "before" parameter is provided, only fetch older messages
                if ($before) {
                    $query->where('created_at', '<', $before);
                }
    
                // Order by created_at descending to get the most recent messages first, then limit the number
                $messages = $query->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    
                // Reverse the collection so messages appear in ascending order (oldest first)
                $messages = $messages->reverse()->values();
    
                // Map messages as before
                $messages = $messages->map(function ($message) {
                    $replyToMessageData = null;
                    if ($message->replyTo) {
                        $replyToMessageData = [
                            'id' => $message->replyTo->id,
                            'sender_name' => $message->replyTo->user->realname ?? 'Unknown',
                            'sender_avatar' => $message->replyTo->user->avatar ?? 'default-avatar.png',
                            'message' => $message->replyTo->message,
                            'created_at' => $message->replyTo->created_at->toIso8601String(),
                        ];
                    }
                    return [
                        'id' => $message->id,
                        'grpchat_id' => $message->grpchat_id,
                        'user_id' => $message->user_id,
                        'sender_name' => $message->user->realname ?? 'Unknown',
                        'sender_avatar' => $message->user->avatar ?? 'default-avatar.png',
                        'message' => $message->status == 0 
                            ? '信息已经撤回' 
                            : ($message->message 
                                ?? ($message->image_url ? '图片信息' 
                                    : ($message->audio_url ? '音频信息' 
                                        : ($message->doc_url ? '文档信息' 
                                            : ($message->video_url ? '视频信息' 
                                                : null))))),
                        'image_url' => $message->status == 0 ? null : $message->image_url,
                        'doc_url' => $message->status == 0 ? null : $message->doc_url,
                        'audio_url' => $message->status == 0 ? null : $message->audio_url,
                        'video_url' => $message->status == 0 ? null : $message->video_url,
                        'status' => $message->status,
                        'created_at' => $message->created_at->toIso8601String(),
                        'reply_to_id' => $message->reply_to_id,
                        'reply_to_message' => $replyToMessageData,
                    ];
                });
            } else {
                // Fetch normal conversation with similar lazy-loading support
                $conversation = Conversation::where('id', $chat_id)
                    ->where(function ($query) use ($authUserId) {
                        $query->where('name', $authUserId)
                              ->orWhere('target', $authUserId);
                    })
                    ->first();
    
                if (!$conversation) {
                    Log::channel('api')->warning('Unauthorized access attempt to conversation.', [
                        'conversation_id' => $chat_id,
                        'auth_user_id' => $authUserId,
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                // Update read timestamp for this user
                $readTimestamps = $conversation->read_timestamps ?? [];
                $readTimestamps[$authUserId] = now()->toDateTimeString();
                $conversation->read_timestamps = $readTimestamps;
                $conversation->save();
    
                $query = Message::with(['user', 'replyTo.user'])
                    ->where('conversation_id', $chat_id)
                    ->where(function($query) use ($authUserId) {
                        $query->where('status', '!=', 0)
                              ->orWhere(function($q) use ($authUserId) {
                                  $q->where('status', '=', 0)
                                    ->where('user_id', '=', $authUserId);
                              });
                    });
    
                if ($before) {
                    $query->where('created_at', '<', $before);
                }
    
                $messages = $query->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    
                $messages = $messages->reverse()->values();
    
                $messages = $messages->map(function ($message) {
                    $replyToMessageData = null;
                    if ($message->replyTo) {
                        $replyToMessageData = [
                            'id' => $message->replyTo->id,
                            'sender_name' => $message->replyTo->user->realname ?? 'Unknown',
                            'sender_avatar' => $message->replyTo->user->avatar ?? 'default-avatar.png',
                            'message' => $message->replyTo->message,
                            'created_at' => $message->replyTo->created_at->toIso8601String(),
                        ];
                    }
                    return [
                        'id' => $message->id,
                        'conversation_id' => $message->conversation_id,
                        'user_id' => $message->user_id,
                        'sender_name' => $message->user->realname ?? 'Unknown',
                        'sender_avatar' => $message->user->avatar ?? 'default-avatar.png',
                        'message' => $message->status == 0 
                            ? '信息已经撤回' 
                            : ($message->message 
                                ?? ($message->image_url ? '图片信息' 
                                    : ($message->audio_url ? '音频信息' 
                                        : ($message->doc_url ? '文档信息' 
                                            : ($message->video_url ? '视频信息' 
                                                : null))))),
                        'image_url' => $message->status == 0 ? null : $message->image_url,
                        'doc_url' => $message->status == 0 ? null : $message->doc_url,
                        'audio_url' => $message->status == 0 ? null : $message->audio_url,
                        'video_url' => $message->status == 0 ? null : $message->video_url,
                        'created_at' => $message->created_at->toIso8601String(),
                        'reply_to_id' => $message->reply_to_id,
                        'reply_to_message' => $replyToMessageData,
                        'status' => $message->status,
                    ];
                });
            }
        
            return response()->json(['messages' => $messages]);
        
        } catch (\Exception $e) {
            Log::channel('api')->error('Error fetching messages.', [
                'chat_id' => $chat_id,
                'auth_user_id' => $authUserId,
                'is_group_chat' => $isGroupChat,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        
            return response()->json(['error' => 'Failed to fetch messages'], 500);
        }
    }

    public function sendMessageFromFlutter(Request $request)
    {
        Log::channel('api')->info("Received Full Flutter Request", [
            'all_input' => $request->all(),
            'headers'   => $request->headers->all(),
            'files'     => $request->allFiles(),
            'ip'        => $request->ip(),
            'method'    => $request->method(),
            'url'       => $request->fullUrl(),
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($request->has('is_group_chat')) {
            $isGroupChat = strtolower($request->input('is_group_chat'));
            if ($isGroupChat === 'true') {
                $request->merge(['is_group_chat' => 1]);
            } elseif ($isGroupChat === 'false') {
                $request->merge(['is_group_chat' => 0]);
            }
        }
        
    
        $rules = [
            'message'         => 'nullable|string',
            'conversation_id' => 'required_if:is_group_chat,0|nullable|exists:conversations,id',
            'grpchat_id'      => 'required_if:is_group_chat,1|nullable|exists:grpchats,id',
            'is_group_chat'   => 'required|boolean',
            'audio'           => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,aac',
            'image'           => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:101200',
            'document'        => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:101200',
            'video'           => 'nullable|file|mimes:mp4,avi,mov,wmv,flv|max:101200',
            'tagged_users'    => 'nullable|array',
            'tagged_users.*'  => 'integer|exists:users,id',
        ];
    
        $isGroupChatInput = $request->input('is_group_chat');
        
        if ($isGroupChatInput) {
            $rules['reply_to_id'] = 'nullable|integer|exists:grpmessage,id';
        } else {
            $rules['reply_to_id'] = 'nullable|integer|exists:messages,id';
        }
    
        try {
            $validated   = $request->validate($rules);
            $isGroupChat = filter_var($validated['is_group_chat'], FILTER_VALIDATE_BOOLEAN);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('api')->warning("Validation failed for sendMessageFromFlutter", [
                'errors'    => $e->errors(),
                'payload'   => $request->except(['audio', 'image', 'document']),
                'timestamp' => now()->toIso8601String(),
            ]);
            return response()->json([
                'error'   => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }
    
        try {
            $authUserId = Auth::id();
            if (!$authUserId) {
                Log::channel('api')->warning('Unauthorized sendMessageFromFlutter attempt.', [
                    'ip'        => $request->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 403);
            }
    
            if ($isGroupChat) {
                $grpChat = Grpchat::where('id', $validated['grpchat_id'])
                    ->whereJsonContains('members', (string)$authUserId)
                    ->first();
    
                if (!$grpChat) {
                    Log::channel('api')->warning('Unauthorized access attempt to group chat.', [
                        'grpchat_id'   => $validated['grpchat_id'],
                        'auth_user_id' => $authUserId,
                        'timestamp'    => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
    
                $settings = $grpChat->settings;
                if ($settings && $settings->mute_chat == 1) {
                    $isOwnerOrAdmin = ($grpChat->owner_id == $authUserId) || in_array($authUserId, $grpChat->admins);
                    if (!$isOwnerOrAdmin) {
                        return response()->json(['error' => '此群聊已对所有成员禁言。'], 403);
                    }
                }
                if ($settings && in_array($authUserId, $settings->mute_members ?? [])) {
                    return response()->json(['error' => '您在此群聊中已被禁言。'], 403);
                }
    
                $messageData = [
                    'grpchat_id'   => $validated['grpchat_id'],
                    'user_id'      => $authUserId,
                    'message'      => $validated['message'] ?? '',
                    'reply_to_id'  => $validated['reply_to_id'] ?? null,
                ];
            } else {
                $conversation = Conversation::where('id', $validated['conversation_id'])
                    ->where(function ($query) use ($authUserId) {
                        $query->where('name', $authUserId)
                              ->orWhere('target', $authUserId);
                    })
                    ->first();
    
                if (!$conversation) {
                    Log::channel('api')->warning('Unauthorized access attempt to conversation.', [
                        'conversation_id' => $validated['conversation_id'],
                        'auth_user_id'    => $authUserId,
                        'timestamp'       => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
    
                $friendId   = ($conversation->name == $authUserId) ? $conversation->target : $conversation->name;
                
                $friendship = Friendlist::where(function ($query) use ($authUserId, $friendId) {
                    $query->where('user_id', $authUserId)->where('friend_id', $friendId);
                })->orWhere(function ($query) use ($authUserId, $friendId) {
                    $query->where('user_id', $friendId)->where('friend_id', $authUserId);
                })->first();
    
                if (!$friendship || $friendship->status != 2) {
                    Log::channel('api')->warning('Message blocked due to invalid friendlist status.', [
                        'friend_id'        => $friendId,
                        'auth_user_id'     => $authUserId,
                        'friendlist_status'=> $friendship->status ?? 'Not Found',
                        'timestamp'        => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => '您无法向此用户发送消息，好友关系未建立。'], 403);
                }
    
                if (!empty($conversation->remove)) {
                    $updatedRemove = array_values(array_filter($conversation->remove, function ($item) use ($friendId) {
                        return $item['id'] != $friendId;
                    }));
                    if (count($updatedRemove) !== count($conversation->remove)) {
                        $conversation->remove = $updatedRemove;
                        $conversation->save();
                    }
                }
    
                $messageData = [
                    'conversation_id' => $validated['conversation_id'],
                    'user_id'         => $authUserId,
                    'message'         => $validated['message'] ?? '',
                    'reply_to_id'     => $validated['reply_to_id'] ?? null,
                ];
            }
    
            $fileTypes = ['audio', 'image', 'document', 'video'];
            foreach ($fileTypes as $type) {
                if ($request->hasFile($type)) {
                    $file      = $request->file($type);
                    $path      = $isGroupChat
                        ? "{$type}s/grpchats/{$validated['grpchat_id']}"
                        : "{$type}s/conversations/{$validated['conversation_id']}";
                    $filename  = time() . '_' . $file->getClientOriginalName();
                    $storedPath= $file->storeAs($path, $filename, 'public');
            
                    if ($type === 'document') {
                        $urlKey = "doc_url";
                    } elseif ($type === 'video') {
                        $urlKey = "video_url";
                    } else {
                        $urlKey = "{$type}_url";
                    }
            
                    $messageData[$urlKey] = $storedPath;
                }
            }
    
            if (!empty($validated['tagged_users'])) {
                $messageData['tagged_users'] = $validated['tagged_users'];
            }
    
            if ($isGroupChat) {
                $grpMessage = Grpmessage::create($messageData);
    
                if (!empty($messageData['tagged_users'])) {
                    $grpMessage->taggedUsers()->attach($messageData['tagged_users']);
                    foreach ($grpMessage->taggedUsers as $taggedUser) {
                        $taggedUser->notify(new UserTagged($grpMessage));
                    }
                }
    
                $grpChat->update([
                    'latest_message'      => $grpMessage->message,
                    'latest_message_time' => $grpMessage->created_at,
                ]);
    
                $grpMessage->load('user');
                $replyToMessageData = null;
                if ($grpMessage->reply_to_id) {
                    $replyToMessage = Grpmessage::find($grpMessage->reply_to_id);
                    if ($replyToMessage) {
                        $replyToMessageData = [
                            'id'            => $replyToMessage->id,
                            'sender_name'   => $replyToMessage->user->realname ?? 'Unknown',
                            'sender_avatar' => $replyToMessage->user->avatar ?? 'default-avatar.png',
                            'message'       => $replyToMessage->message,
                            'created_at'    => $replyToMessage->created_at->toIso8601String(),
                        ];
                    } else {
                        Log::channel('api')->warning('Reply to message not found.', [
                            'reply_to_id'  => $grpMessage->reply_to_id,
                            'grpmessage_id'=> $grpMessage->id,
                            'timestamp'    => now()->toIso8601String(),
                        ]);
                    }
                }
    
                $responseMessage = [
                    'type'             => 'grpchat',
                    'grpchat_id'       => $grpMessage->grpchat_id,
                    'message'          => $grpMessage->message 
                        ?? ($grpMessage->image_url ? '图片信息' 
                            : ($grpMessage->audio_url ? '音频信息' 
                                : ($grpMessage->doc_url ? '文档信息' 
                                    : ($grpMessage->video_url ? '视频信息' : null)))),
                    'user_id'          => $grpMessage->user_id,
                    'sender_name'      => $grpMessage->user->realname ?? 'Unknown',
                    'sender_avatar'    => $grpMessage->user->avatar ?? 'default-avatar.png',
                    'audio_url'        => $grpMessage->audio_url ?? null,
                    'video_url'        => $grpMessage->video_url ?? null,
                    'doc_url'          => $grpMessage->doc_url ?? null,
                    'image_url'        => $grpMessage->image_url ?? null,
                    'created_at'       => $grpMessage->created_at->toIso8601String(),
                    'id'               => $grpMessage->id,
                    'reply_to_id'      => $grpMessage->reply_to_id,
                    'reply_to_user_name' => $replyToMessageData
                        ? ($replyToMessageData['sender_name'] ?? null)
                        : null,
                    'reply_to_message' => $replyToMessageData,
                    'tagged_users'     => $grpMessage->taggedUsers->map(function($u) {
                        return ['id' => $u->id, 'name' => $u->realname];
                    }),
                ];
                
                try {
                    foreach ($grpChat->members as $memberId){
                        if ($memberId != $grpMessage->user_id) { // Exclude sender
                            $broadcastData = [
                                'type' => 'grpchat',
                                'grpchat_id' => (int) $grpMessage->grpchat_id,
                                'message' => $grpMessage->message 
                                    ?? ($grpMessage->image_url ? '图片信息' 
                                        : ($grpMessage->audio_url ? '音频信息' 
                                            : ($grpMessage->doc_url ? '文档信息' 
                                                : ($grpMessage->video_url ? '视频信息' : null)))),
                                'user_id' => $grpMessage->user_id,
                                'sender_name' => $grpMessage->user->realname ?? '未知',
                                'sender_nickname' => $grpMessage->user->realname ?? '未知',
                                'sender_avatar' => $grpMessage->user->avatar ?? 'default-avatar.png',
                                'audio_url' => $grpMessage->audio_url ?? null,
                                'doc_url' => $grpMessage->doc_url ?? null,
                                'image_url' => $grpMessage->image_url ?? null,
                                'video_url' => $grpMessage->video_url ?? null,
                                'created_at' => $grpMessage->created_at->toIso8601String(),
                                'id' => $grpMessage->id,
                                'tagged_users' => $grpMessage->taggedUsers->map(function ($u) {
                                    return ['id' => $u->id, 'name' => $u->realname];
                                }),
                                'reply_to_id' => $grpMessage->reply_to_id,
                                'reply_to_user_name' => $grpMessage->replyTo->user->realname ?? null,
                                'reply_to_message' => $grpMessage->replyTo->message ?? null,
                            ];
                    
                            broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                        }
                    }
    
                } catch (\Exception $e) {
                    Log::channel('api')->error('Error in broadcasting group message.', [
                        'error'         => $e->getMessage(),
                        'trace'         => $e->getTraceAsString(),
                        'grpmessage_id' => $grpMessage->id,
                        'timestamp'     => now()->toIso8601String(),
                    ]);
                }
            } else {
                $message = Message::create($messageData);
    
                if (!empty($messageData['tagged_users'])) {
                    $message->taggedUsers()->attach($messageData['tagged_users']);
                    foreach ($message->taggedUsers as $taggedUser) {
                        $taggedUser->notify(new UserTagged($message));
                    }
                }
    
                $conversation->update([
                    'latest_message'      => $message->message,
                    'latest_message_time' => $message->created_at,
                ]);
    
                $message->load('user');
    
                $replyToMessageData = null;
                if ($message->reply_to_id) {
                    $replyToMessage = Message::find($message->reply_to_id);
                    if ($replyToMessage) {
                        $replyToMessageData = [
                            'id'            => $replyToMessage->id,
                            'sender_name'   => $replyToMessage->user->realname ?? 'Unknown',
                            'sender_avatar' => $replyToMessage->user->avatar ?? 'default-avatar.png',
                            'message'       => $replyToMessage->message,
                            'created_at'    => $replyToMessage->created_at->toIso8601String(),
                        ];
                    } else {
                        Log::channel('api')->warning('Reply to message not found.', [
                            'reply_to_id' => $message->reply_to_id,
                            'message_id'  => $message->id,
                            'timestamp'   => now()->toIso8601String(),
                        ]);
                    }
                }
    
                $responseMessage = [
                    'type'             => 'conversation',
                    'conversation_id'  => $message->conversation_id,
                    'message'          => $message->message 
                        ?? ($message->image_url ? '图片信息' 
                            : ($message->audio_url ? '音频信息' 
                                : ($message->doc_url ? '文档信息' 
                                    : ($message->video_url ? '视频信息' : null)))),
                    'user_id'          => $message->user_id,
                    'sender_name'      => $message->user->realname ?? 'Unknown',
                    'sender_nickname'  => $message->user->realname ?? 'Unknown',
                    'sender_avatar'    => $message->user->avatar ?? 'default-avatar.png',
                    'audio_url'        => $message->audio_url ?? null,
                    'doc_url'          => $message->doc_url ?? null,
                    'image_url'        => $message->image_url ?? null,
                    'video_url'        => $message->video_url ?? null,
                    'created_at'       => $message->created_at->toIso8601String(),
                    'id'               => $message->id,
                    'reply_to_id'      => $message->reply_to_id,
                    'reply_to_user_name' => $replyToMessageData
                        ? ($replyToMessageData['sender_name'] ?? null)
                        : null,
                    'reply_to_message' => $replyToMessageData,
                    'tagged_users'     => $message->taggedUsers->map(function ($u) {
                        return ['id' => $u->id, 'name' => $u->realname];
                    }),
                ];
                
                $recipientId = $conversation->getOtherUserId($message->user_id);
                $me = $message->user_id;
    
                broadcast(new MessageSent($message, $recipientId, $me))->toOthers();;
            }
    
            return response()->json($responseMessage, 200);
        } catch (\Exception $e) {
            Log::channel('api')->error('Error sending message from Flutter.', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ]);
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }
    
    private function extractUsernames($message)
    {
        preg_match_all('/@(\w+)/', $message, $matches);
        return $matches[1] ?? [];
    }

    public function getConversations(Request $request)
    {
        try {
            $authUserId = Auth::id();
    
            if (!$authUserId) {
                Log::channel('api')->warning('Unauthorized access attempt to getConversations.', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            // Enable query logging for debugging
            DB::enableQueryLog();
    
            // Fetch personal conversations
            $conversations = Conversation::with(['targetUser', 'latestMessage'])
                ->where(function ($query) use ($authUserId) {
                    $query->where('name', $authUserId)
                          ->orWhere('target', $authUserId);
                })
                ->where(function ($query) use ($authUserId) {
                    $query->whereNull('remove')
                          ->orWhereRaw('NOT JSON_CONTAINS(remove, ?, \'$\')', [
                              json_encode(['id' => $authUserId])
                          ]);
                })
                ->get();
    
            // Fetch group chats
            $grpChats = Grpchat::with(['latestMessage'])
                ->whereJsonContains('members', (string) $authUserId) // Ensure 'members' are strings
                ->where(function ($query) use ($authUserId) {
                    $query->whereNull('remove')
                          ->orWhereRaw('NOT JSON_CONTAINS(remove, ?, \'$\')', [
                              json_encode(['id' => $authUserId])
                          ]);
                })
                ->get();
    
            // Log the executed queries for debugging
            $queries = DB::getQueryLog();
            DB::flushQueryLog(); // Clear the query log
    
            $transformedConversations = $conversations->map(function ($conversation) use ($authUserId) {
                $readTimestamps = $conversation->read_timestamps ?? [];
                $authUserIdStr = (string) $authUserId;
                $lastRead = isset($readTimestamps[$authUserIdStr]) ? Carbon::parse($readTimestamps[$authUserIdStr]) : null;
                $unreadCount = $lastRead 
                    ? $conversation->messages()->where('created_at', '>', $lastRead)->count()
                    : $conversation->messages()->count();
            
                $otherUserId = $conversation->getOtherUserId($authUserId);
                $otherUser = $conversation->targetUser;
            
                if ($otherUser->id !== $otherUserId) {
                    $otherUser = User::find($otherUserId);
                }
            
                $latestMessage = $conversation->messages()->orderBy('created_at', 'desc')->first();
                $latestMessageContent = '暂没消息';
                $latestMessageTime = null;
            
                if ($latestMessage) {
                    if ($latestMessage->status === 0) {
                        $latestMessageContent = '【信息已经撤回】';
                    } else {
                        $latestMessageContent = $latestMessage->message 
                            ?? ($latestMessage->image_url ? '图片信息' 
                                : ($latestMessage->audio_url ? '音频信息' 
                                    : ($latestMessage->doc_url ? '文档信息' 
                                        : ($latestMessage->video_url ? '视频信息' : '暂没消息'))));
                    }
                    $latestMessageTime = $latestMessage->created_at ? $latestMessage->created_at->toIso8601String() : null;
                } else {
                    Log::channel('api')->info('No latest message found for conversation.', [
                        'conversation_id' => $conversation->id,
                    ]);
                }
            
                return [
                    'type' => 'conversation',
                    'id' => $conversation->id,
                    'name' => $otherUser->realname ?? 'Unknown User',
                    'avatar' => $otherUser->avatar ?? null,
                    'audio_url' => $latestMessage->audio_url ?? null,
                    'doc_url' => $latestMessage->doc_url ?? null,
                    'video_url' => $latestMessage->video_url ?? null,
                    'image_url' => $latestMessage->image_url ?? null,
                    'latest_message' => $latestMessageContent,
                    'latest_message_time' => $latestMessageTime,
                    'unread_count' => $unreadCount,
                ];
            });
            
            $transformedGrpchats = $grpChats->map(function ($grpchat) use ($authUserId) {
                $readTimestamps = $grpchat->read_timestamps ?? [];
                $authUserIdStr = (string) $authUserId;
                $lastRead = isset($readTimestamps[$authUserIdStr]) ? Carbon::parse($readTimestamps[$authUserIdStr]) : null;
                $unreadCount = $lastRead 
                    ? $grpchat->messages()->where('created_at', '>', $lastRead)->count()
                    : $grpchat->messages()->count();
            
                $latestMessage = $grpchat->latestMessage;
                $latestMessageContent = '暂没消息';
                $latestMessageTime = null;
            
                if ($latestMessage) {
                    if ($latestMessage->status === 0) {
                        $latestMessageContent = '【信息已经撤回】';
                    } else {
                        // Determine the content type
                        $latestMessageContent = $latestMessage->message 
                            ?? ($latestMessage->image_url ? '图片信息' 
                                : ($latestMessage->audio_url ? '音频信息' 
                                    : ($latestMessage->doc_url ? '文档信息' 
                                        : ($latestMessage->video_url ? '视频信息' : '有新消息'))));
                    }
                    // Get the timestamp in ISO 8601 format
                    $latestMessageTime = $latestMessage->created_at ? $latestMessage->created_at->toIso8601String() : null;
                } else {
                    // Log a message if no latest message is found
                    Log::channel('api')->info('暂没消息', [
                        'grpchat_id' => $grpchat->id,
                    ]);
                }
            
                return [
                    'type' => 'grpchat',
                    'id' => $grpchat->id,
                    'name' => $grpchat->chatname ?? 'Unnamed Group',
                    'avatar' => $grpchat->avatar ?? null,
                    'audio_url' => $latestMessage->audio_url ?? null,
                    'doc_url' => $latestMessage->doc_url ?? null,
                    'video_url' => $latestMessage->video_url ?? null,
                    'image_url' => $latestMessage->image_url ?? null,
                    'latest_message' => $latestMessageContent,
                    'latest_message_time' => $latestMessageTime,
                    'unread_count' => $unreadCount,
                ];
            });

    
            // Merge and sort chats based on latest_message_time descending
            $sortedChats = $transformedConversations->merge($transformedGrpchats)
                ->sortByDesc(function ($chat) {
                    return $chat['latest_message_time'] ? Carbon::parse($chat['latest_message_time']) : Carbon::parse('1970-01-01');
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'chats' => $sortedChats, // Unified and sorted list
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error('Error fetching conversations.', [
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);
    
            return response()->json(['error' => 'Failed to fetch conversations'], 500);
        }
    }
    
    public function startConversation(Request $request)
    {

        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id',
        ]);

        $authUserId = auth()->id();
        $friendId = $validated['friend_id'];

        if ($authUserId === $friendId) {
            $this->logger->warning('Attempt to start conversation with self.', [
                'user_id' => $authUserId,
                'friend_id' => $friendId,
            ]);
            return response()->json([
                'message' => 'You cannot start a conversation with yourself.'
            ], 400);
        }

        $conversation = Conversation::where(function ($query) use ($authUserId, $friendId) {
            $query->where('name', $authUserId)->where('target', $friendId);
        })->orWhere(function ($query) use ($authUserId, $friendId) {
            $query->where('name', $friendId)->where('target', $authUserId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'name' => $authUserId,
                'target' => $friendId,
            ]);

        } else {

        }

        $lastMessage = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->first();


        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'conversation_id' => $conversation->id,
            'last_message_date' => $lastMessage ? $lastMessage->created_at : null,
            'messages' => $messages,
        ]);
    }
    
    public function settings(Request $request, $conversationId)
    {
        $authUserId = Auth::id();

        // Find the conversation where the user is a participant
        $conversation = Conversation::where('id', $conversationId)
            ->where(function ($query) use ($authUserId) {
                $query->where('name', $authUserId)
                      ->orWhere('target', $authUserId);
            })
            ->first();
    
        if (!$conversation) {
            Log::warning('Unauthorized Access Attempt', [
                'conversation_id' => $conversationId,
                'request_user' => $authUserId,
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    
        // Determine the other user in the conversation
        $otherUserId = ($conversation->name == $authUserId) ? $conversation->target : $conversation->name;
    
        // Fetch the details of the other user
        $otherUser = User::find($otherUserId, ['id', 'name', 'avatar']);
    
        if (!$otherUser) {
            Log::error('Target user not found', [
                'target_user_id' => $otherUserId,
                'conversation_id' => $conversationId,
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }
        
        // Return the conversation details
        return response()->json([
            'chatname' => $otherUser->name,
            'avatar' => $otherUser->avatar ?? 'default-avatar.png', // Default avatar if not available
        ]);
    }
    
    public function chathistory()
    {
        // 分页加载个人对话，每页 10 条
        $personalConversations = Conversation::with(['user', 'targetUser'])
            ->paginate(10, ['*'], 'personal_page');
    
        // 分页加载群组对话，每页 10 条
        $groupChats = Grpchat::with(['messages.user'])
            ->paginate(10, ['*'], 'group_page');
    
        return view('pages.app.chat_history', compact('personalConversations', 'groupChats'))->with('title', '对话记录');
    }
    
    public function getPersonalData(Request $request)
    {
        $conversations = Conversation::with(['user', 'targetUser'])->select('conversations.*');

        return DataTables::of($conversations)
            ->addColumn('actions', function ($conversation) {
                return '<button class="btn btn-sm btn-primary view-details-btn" data-conversation-id="' . $conversation->id . '" data-type="personal">查看详情</button>';
            })
            ->editColumn('targetUser.name', function ($conversation) {
                return optional($conversation->targetUser)->name ?: '无';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function getGroupData(Request $request)
    {
        $groupChats = Grpchat::select('grpchats.*', DB::raw('JSON_LENGTH(members) as members_count'));
    
        return DataTables::of($groupChats)
            ->filter(function ($query) use ($request) {
                if ($request->input('search.value')) {
                    $search = $request->input('search.value');
                    // Only search on the chatname column
                    $query->where('chatname', 'like', "%{$search}%");
                }
            })
            ->addColumn('actions', function ($grpchat) {
                return '<button class="btn btn-sm btn-primary view-details-btn" data-conversation-id="' . $grpchat->id . '" data-type="group">查看详情</button>';
            })
            ->addColumn('avatar', function ($grpchat) {
                if ($grpchat->avatar) {
                    return '<img src="' . Storage::url($grpchat->avatar) . '" alt="头像" width="50" onerror="this.onerror=null;this.src=\'https://via.placeholder.com/50\';">';
                } else {
                    return '<img src="https://via.placeholder.com/50" alt="头像" width="50">';
                }
            })
            ->rawColumns(['actions', 'avatar'])
            ->make(true);
    }


    public function getPersonalMessages($id)
    {
        $conversation = Conversation::with(['messages.user'])->findOrFail($id);

        return response()->json([
            'messages' => $conversation->messages->map(function($message) {
                return [
                    'id' => $message->id,
                    'sender' => $message->user->name ?? '未知',
                    'content' => $message->message,
                    'image_url' => $message->image_url ? Storage::url($message->image_url) : null,
                    'audio_url' => $message->audio_url ? Storage::url($message->audio_url) : null,
                    'doc_url' => $message->doc_url ? Storage::url($message->doc_url) : null,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    public function getGroupMessages($id)
    {
        $grpchat = Grpchat::with(['messages.user'])->findOrFail($id);

        return response()->json([
            'messages' => $grpchat->messages->map(function($message) {
                return [
                    'id' => $message->id,
                    'sender' => $message->user->name ?? '未知',
                    'content' => $message->message,
                    'image_url' => $message->image_url ? Storage::url($message->image_url) : null,
                    'audio_url' => $message->audio_url ? Storage::url($message->audio_url) : null,
                    'doc_url' => $message->doc_url ? Storage::url($message->doc_url) : null,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    public function updateMessage(Request $request)
    {
        // 验证请求
        $validated = $request->validate([
            'message_type' => 'required|in:personal,group',
            'message_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        try {
            if ($validated['message_type'] === 'personal') {
                $message = Message::findOrFail($validated['message_id']);
                $message->message = $validated['message'];
                $message->save();
            } else {
                $grpMessage = Grpmessage::findOrFail($validated['message_id']);
                $grpMessage->message = $validated['message'];
                $grpMessage->save();
            }

            return response()->json(['success' => true, 'message' => '消息更新成功。']);
        } catch (\Exception $e) {
            \Log::error('更新消息时出错', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => '消息更新失败。'], 500);
        }
    }
    
    public function recallMessage(Request $request)
    {
        $rules = [
            'message_id' => 'required|integer',
            'is_group_chat' => 'required|boolean',
        ];

        // Validate the request
        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
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

        // Determine the message model based on chat type
        $messageModel = $isGroupChat ? Grpmessage::class : Message::class;

        try {
            // Retrieve the message with related user and reply information
            $message = $messageModel::with(['user', 'replyTo.user'])
                ->where('id', $messageId)
                ->first();

            // Check if the message exists
            if (!$message) {
                Log::channel('api')->warning('Message not found for recallMessage.', [
                    'message_id' => $messageId,
                    'is_group_chat' => $isGroupChat,
                    'user_id' => $authUserId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '消息未找到'], 404);
            }

            $isAuthorized = false;

            // Handle group chat authorization
            if ($isGroupChat) {
                // Retrieve grpchat_id from the message
                $grpchatId = $message->grpchat_id;

                // Fetch group chat details
                $grpchat = Grpchat::find($grpchatId);

                // Check if the group chat exists
                if (!$grpchat) {
                    Log::channel('api')->warning('Group chat not found for message.', [
                        'message_id' => $messageId,
                        'grpchat_id' => $grpchatId,
                        'user_id' => $authUserId,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => '群聊未找到'], 404);
                }

                // Check if the user is the owner or an admin of the group chat
                $isOwnerOrAdmin = ($grpchat->owner_id == $authUserId) ||
                                  (is_array($grpchat->admins) && in_array($authUserId, $grpchat->admins));

                if ($isOwnerOrAdmin) {
                    $isAuthorized = true;
                }
            }

            // Allow users to recall their own messages
            if ($message->user_id === $authUserId) {
                $isAuthorized = true;
            }

            // Deny access if not authorized
            if (!$isAuthorized) {
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

            // Update the message status to recalled
            $message->status = 0;
            $message->save();

            // Reload the updated message with conversation
            $updatedMessage = $messageModel::with(['user', 'replyTo.user'])->find($messageId);

            // Check if the updated message exists
            if (!$updatedMessage) {
                Log::channel('api')->warning('Updated message not found after recall.', [
                    'message_id' => $messageId,
                    'is_group_chat' => $isGroupChat,
                    'user_id' => $authUserId,
                    'timestamp' => now()->toIso8601String(),
                ]);
                return response()->json(['error' => '无法获取已更新的消息'], 500);
            }

            // Prepare broadcast data based on chat type
            if ($isGroupChat) {
                $broadcastData = [
                    'id'                  => $updatedMessage->id,
                    'message'             => '【信息已经撤回】',
                    'created_at'          => $updatedMessage->created_at->toIso8601String(),
                    'type'                => 'grecall',
                    'grp_chat_id'         => $updatedMessage->grpchat_id,
                    'status'              => $updatedMessage->status,
                    'sender_name'         => $updatedMessage->user->realname ?? '未知',
                    'sender_avatar'       => $updatedMessage->user->avatar ?? 'default-avatar.png',
                    'reply_to_id'         => $updatedMessage->reply_to_id,
                    'reply_to_user_name'  => $updatedMessage->replyTo->user->realname ?? null,
                    'reply_to_message'    => $updatedMessage->replyTo->message ?? null,
                    'tagged_users'        => [],
                    'user_id' => $authUserId,
                ];
            } else {
                $broadcastData = [
                    'id' => $updatedMessage->id,
                    'message' => '【信息已经撤回】',
                    'created_at' => $updatedMessage->created_at->toIso8601String(),
                    'type' => 'crecall',
                    // 'grp_chat_id' is intentionally omitted for one-on-one conversations
                ];
            }

            // Broadcast the recall event
            if ($isGroupChat) {
                // Fetch group chat details
                $grpchat = Grpchat::find($updatedMessage->grpchat_id);
                $groupMembers = $grpchat->members;

                foreach ($groupMembers as $memberId) {
                    if ($memberId != $authUserId) { // Exclude the sender
                        try {
                            
                            broadcast(new GroupMessageSent($broadcastData, $memberId))->toOthers();
                            
                        } catch (\Exception $e) {
                            Log::channel('api')->error('Error broadcasting to group member.', [
                                'member_id' => $memberId,
                                'grpmessage_id' => $updatedMessage->id,
                                'error' => $e->getMessage(),
                                'timestamp' => now()->toIso8601String(),
                            ]);
                        }
                    }
                }
            } else {
                // Fetch the conversation using conversation_id
                $conversation = DB::table('conversations')
                    ->where('id', $updatedMessage->conversation_id)
                    ->first();

                if (!$conversation) {
                    Log::channel('api')->warning('Conversation not found for one-on-one recall.', [
                        'message_id' => $messageId,
                        'user_id' => $authUserId,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => '会话信息缺失'], 500);
                }

                // Determine the recipient ID based on name and target
                $recipientId = ($conversation->name == $authUserId) ? $conversation->target : $conversation->name;

                try {
                    // Broadcast the crecall event by passing the Message model
                    $me = $authUserId;
                    broadcast(new MessageSent($updatedMessage, $recipientId, $me, 'crecall'))->toOthers();
                } catch (\Exception $e) {
                    Log::channel('api')->error('Error broadcasting crecall event.', [
                        'recipient_id' => $recipientId,
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    return response()->json(['error' => '广播撤回事件时发生错误'], 500);
                }
            }

            // Return successful response
            return response()->json([
                'message_id' => $messageId,
                'is_group_chat' => $isGroupChat,
                'status' => 'success',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Exception $e) {
            // Log any unexpected errors
            Log::channel('api')->error('Error in recallMessage.', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'is_group_chat' => $isGroupChat,
                'user_id' => $authUserId,
                'timestamp' => now()->toIso8601String(),
            ]);
            return response()->json(['error' => '撤回消息时发生错误'], 500);
        }
    }
    
    public function removeConversation(Request $request)
    {
        try {
            // Step 1: Authenticate User
            $user = Auth::user();
    
            if (!$user) {
                Log::warning('Unauthorized removal attempt to removeConversation.', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            // Step 2: Validate Input
            $validator = Validator::make($request->all(), [
                'chat_id' => 'required|integer',
                'is_group_chat' => 'required|boolean',
            ]);
    
            if ($validator->fails()) {
                Log::warning('Invalid removeConversation request data', [
                    'errors' => $validator->errors(),
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => 'Invalid input', 'details' => $validator->errors()], 400);
            }
    
            $chatId = $request->input('chat_id');
            $isGroupChat = $request->input('is_group_chat');
    
            if ($isGroupChat) {
                Log::warning('Attempted to remove group chat via removeConversation endpoint', [
                    'grpchat_id' => $chatId,
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => '此接口不允许移除群聊。'], 403); // "This endpoint does not allow removing group chats."
            } else {
                $conversation = Conversation::find($chatId);
    
                if (!$conversation) {
                    Log::warning('Personal conversation not found', [
                        'conversation_id' => $chatId,
                        'user_id' => $user->id,
                    ]);
                    return response()->json(['error' => '未找到个人聊天记录。'], 404);
                }
    
                if ($conversation->name != $user->id && $conversation->target != $user->id) {
                    Log::warning('Unauthorized personal conversation removal attempt', [
                        'conversation_id' => $chatId,
                        'user_id' => $user->id,
                    ]);
                    return response()->json(['error' => '您无权删除此聊天。'], 403);
                }
    
                // Retrieve or initialize the `remove` field as an array
                $removeLog = $conversation->remove ?? [];
    
                // Check if the user has already removed this conversation
                if (collect($removeLog)->contains('id', $user->id)) {
                    return response()->json(['success' => true, 'message' => '聊天已被移除。'], 200); // "Chat has been removed."
                }
    
                // Record the removal action
                $removeLog[] = [
                    'id' => $user->id,
                    'timestamp' => now()->toIso8601String(),
                ];
    
                // Update the `remove` field
                $conversation->remove = $removeLog;
                $conversation->save();
    
                return response()->json([
                    'success' => true,
                    'message' => '个人聊天已成功移除。',
                    'remove_log' => $removeLog,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('api')->error('Error removing conversation.', [
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);
    
            return response()->json(['error' => 'Failed to remove conversation'], 500);
        }
    }
}
