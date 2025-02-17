<?php

use Illuminate\Http\Request;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GrpchatController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\RobotController;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserBankController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\VersionController;


// Public Route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('/validate-token', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'message' => 'Token is valid.',
        'user' => $request->user(),
    ]);
});

// Protected Chat Routes
Route::middleware('auth:sanctum')->group(function () {
    // Conversations
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::post('/conversations/start', [ChatController::class, 'startConversation']);

    // Messages
    Route::get('/messages/{conversation_id}', [ChatController::class, 'getMessages']);
    Route::post('/flutter/messages', [ChatController::class, 'sendMessageFromFlutter']);
});

//Wallet
Route::middleware('auth:sanctum')->post('/deposits', [DepositController::class, 'store']);
Route::get('/gateways', [GatewayController::class, 'index']);
Route::get('/transactions/{user_id}', [TransactionController::class, 'getUserTransactions']);
Route::get('/wallets/{user_id}', [WalletController::class, 'getBalance']);

//Withdrawal
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/withdrawals', [WithdrawalController::class, 'withdraw']);
    Route::post('/withdrawals/approve', [WithdrawalController::class, 'approve']);
    Route::post('/withdrawals/reject', [WithdrawalController::class, 'reject']);
    Route::get('/withdrawals/pending', [WithdrawalController::class, 'getPendingWithdrawals']);
    Route::get('/checkbank', [UserBankController::class, 'checkBank']);
    Route::post('/addbank', [UserBankController::class, 'addBank']);
});

//Auth
Route::post('/login', [AuthController::class, 'apiLogin']);
Route::post('/register', [AuthController::class, 'apiRegister']);
Route::get('/update', [VersionController::class, 'getLatestVersion']);

Route::post('/forgot-password', function (Request $request) {
    // Log the incoming request
    Log::info('Forgot Password Request Received', [
        'email' => $request->email,
        'ip' => $request->ip(),
    ]);

    try {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        // Log the response status
        Log::info('Forgot Password Status', [
            'email' => $request->email,
            'status' => $status,
        ]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'We have emailed your password reset link!'])
            : response()->json(['message' => 'We can\'t find a user with that email address.'], 404);
    } catch (\Exception $e) {
        // Log the error
        Log::error('Forgot Password Error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'An error occurred while processing your request.'
        ], 500);
    }
});

//Ticket
Route::middleware('auth:sanctum')->group(function () {
    // Tickets
    Route::get('/tickets', [CustomerServiceController::class, 'apiIndex']);
    Route::post('/tickets', [CustomerServiceController::class, 'apiStore']);
    Route::get('/tickets/{id}', [CustomerServiceController::class, 'apiShow']);
    Route::post('/tickets/{id}/message', [CustomerServiceController::class, 'apiStoreMessage']);
});

//Friends
Route::middleware('auth:sanctum')->post('/add_friend', [FriendController::class, 'addFriend']);
Route::middleware('auth:sanctum')->put('/friend/{id}/status', [FriendController::class, 'updateFriendStatus']);
Route::middleware('auth:sanctum')->get('/user/search', [FriendController::class, 'searchFriend']);
Route::middleware('auth:sanctum')->get('/friends', [FriendController::class, 'getFriends']);
Route::middleware('auth:sanctum')->post('/user/update', [UserController::class, 'updateProfile']);
Route::middleware('auth:sanctum')->get('/flutterprofile', [UserController::class, 'flutterprofile']);
Route::middleware('auth:sanctum')->get('/incoming_requests', [FriendController::class, 'getIncomingRequests']);

Route::middleware('auth:sanctum')->get('/{normalchat}/settings', [ChatController::class, 'settings'])->name('normalchats.settings');
Route::middleware('auth:sanctum')->post('/chat/recall', [ChatController::class, 'recallMessage'])->name('chat.recall');
Route::middleware('auth:sanctum')->post('/chat/removechat', [ChatController::class, 'removeConversation'])->name('chat.removechat');

//Grp chat
Route::middleware('auth:sanctum')->get('/friends/list', [GrpchatController::class, 'getFriendList'])->name('friends.list');
Route::middleware('auth:sanctum')->post('/grpchats/create', [GrpchatController::class, 'createGrpChat'])->name('grpchats.create');
Route::middleware('auth:sanctum')->get('/grpchats/{grpchat}/settings', [GrpchatController::class, 'settings'])->name('grpchats.settings');
Route::middleware('auth:sanctum')->get('/grpchats/{grpchat}/members', [GrpchatController::class, 'getMemberList'])->name('grpchats.getMemberList');
Route::middleware('auth:sanctum')->post('/grpchats/{grpchat}/delete', [GrpchatController::class, 'deleteChat'])->name('grpchats.deleteChat');
Route::middleware('auth:sanctum')->post('/grpchats/{grpchat}/update', [GrpchatController::class, 'updateSettings'])->name('grpchats.updateSettings');
Route::middleware('auth:sanctum')->post('/grpchat/{grpchat}/remove-member', [GrpchatController::class, 'removeMember'])->name('grpchats.removeMember');
Route::middleware('auth:sanctum')->post('/grpchat/{grpchat}/add-member', [GrpchatController::class, 'addMember'])->name('grpchats.add-member');
Route::middleware('auth:sanctum')->get('/grpchat/{grpchat}/available-members', [GrpchatController::class, 'getAvailableMembers'])->name('grpchats.available-members');
Route::middleware('auth:sanctum')->get('/grpchat/{grpchatId}/announcement', [GrpchatController::class, 'getAnnouncement']);
Route::middleware('auth:sanctum')->post('/grpchat/{grpchatId}/quit', [GrpchatController::class, 'quitGrpChat'])->name('grpchats.quit');

Route::middleware('auth:sanctum')->get('/grpchat/{grpchatId}/admins', [GrpchatController::class, 'getAdmins'])->name('grpchats.getAdminsList');
Route::middleware('auth:sanctum')->post('/grpchat/{grpchat}/add-admin', [GrpchatController::class, 'addAdmin'])->name('grpchats.addAdmin');
Route::middleware('auth:sanctum')->post('/grpchat/{grpchat}/remove-admin', [GrpchatController::class, 'removeAdmin'])->name('grpchats.removeAdmin');
Route::middleware('auth:sanctum')->get('/grpchat/{grpchatId}/available-admins', [GrpchatController::class, 'availableAdmin'])->name('grpchats.available-admins');
Route::middleware('auth:sanctum')->get('/robot/search-users', [RobotController::class, 'searchUsers'])->name('robot.searchUsers');

/*
Route::post('/grpchat/{grpchat}/add-member', [GrpchatController::class, 'addMember'])->name('grpchats.add-member');
Route::get('/grpchat/{grpchat}/available-members', [GrpchatController::class, 'getAvailableMembers'])->name('grpchats.available-members');

Route::post('/grpchats/{grpchat}/messages', [GrpchatController::class, 'sendMessage'])->name('grpchats.sendMessage');
Route::get('/grpchats/{grpchat}/messages', [GrpchatController::class, 'getMessages'])->name('grpchats.getMessages');
*/

Route::get('/grpchats', [GrpchatController::class, 'showGroupChats'])->name('grpchats.index');