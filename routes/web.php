<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletActionController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\BatchUserController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\RobotController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GrpchatController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\FirebaseTokenController;
use App\Http\Controllers\CategoryController;

// Include theme-specific routes
require_once 'theme-routes.php';

// ---------------------------------
// Public Routes
// ---------------------------------

// Barebone Route
Route::get('/barebone', function () {
    return view('barebone', ['title' => 'This is Title']);
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---------------------------------
// Protected Routes (Requires Authentication)
// ---------------------------------


Route::middleware('auth')->group(function () {
    
    // Dashboard Route
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Grouping all /app/ routes under 'app' prefix
    Route::prefix('app')->group(function () {
        
        // ---------------------------------
        // Contacts Routes
        // ---------------------------------
        Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
        Route::put('/contacts/{id}', [ContactsController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{id}', [ContactsController::class, 'destroy'])->name('contacts.destroy');

        // ---------------------------------
        // Chat Routes
        // ---------------------------------
        Route::get('/chat', [ChatController::class, 'index'])->name('chat');
        Route::post('/send-message', [ChatController::class, 'sendMessage']);
        //Route::get('/send-message', [ChatController::class, 'sendMessage']);
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'getMessages'])->name('conversations.messages');
        Route::get('/chathistory', [ChatController::class, 'chathistory'])->name('chathistory');
        Route::post('/chat/update-message', [ChatController::class, 'updateMessage'])->name('chat.updateMessage');
        
        Route::get('/chathistory/personal-data', [ChatController::class, 'getPersonalData'])->name('chathistory.personalData');
        Route::get('/chathistory/group-data', [ChatController::class, 'getGroupData'])->name('chathistory.groupData');
        Route::get('/chathistory/personal/{id}/messages', [ChatController::class, 'getPersonalMessages'])->name('chathistory.personalMessages');
        Route::get('/chathistory/group/{id}/messages', [ChatController::class, 'getGroupMessages'])->name('chathistory.groupMessages');

        // ---------------------------------
        // User Routes
        // ---------------------------------
        Route::prefix('user')->group(function () {
            Route::get('/create', [UserController::class, 'create'])->name('user.create');
            Route::post('/store', [UserController::class, 'store'])->name('user.store');

            Route::get('/profile', [UserController::class, 'profile'])->name('user.profile');
            Route::put('/update-profile', [UserController::class, 'updateProfileWeb'])->name('user.update-profile');
            Route::put('/update-robot', [UserController::class, 'updateProfileRobot'])->name('user.update-robot');
            Route::put('/update-password', [UserController::class, 'updatePasswordWeb'])->name('user.update-password');
            Route::put('/update-security-pin', [UserController::class, 'updateSecurityPinWeb'])->name('user.update-security-pin');

            Route::get('/settings', [UserController::class, 'settings'])->name('user.settings');
            Route::get('/userlist', [UserController::class, 'userList'])->name('user.list');
            Route::get('/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
            Route::put('/update/{id}', [UserController::class, 'updateUser'])->name('user.updateUser');
            Route::put('/{id}/update-bank', [UserController::class, 'updateBank'])->name('user.updateBank');
            Route::put('/{id}/updateWallet', [UserController::class, 'updateWallet'])->name('user.updateWallet');
            Route::post('/user/reset-password/{id}', [UserController::class, 'resetPassword'])->name('user.reset-password');
            Route::post('/users/{id}/chat', [UserController::class, 'initiateChat'])->name('user.chat');
        });

        // ---------------------------------
        // Wallet Actions Routes
        // ---------------------------------
        Route::get('/walletaction', [WalletActionController::class, 'index'])->name('wallet.action');

       
        Route::post('/walletaction/deposit', [WalletActionController::class, 'deposit'])->name('wallet.action.deposit');
        Route::post('/walletaction/freeze', [WalletActionController::class, 'freeze'])->name('wallet.action.freeze');
        Route::post('/walletaction/adjust', [WalletActionController::class, 'adjustWallet'])->name('wallet.action.adjust');
        Route::post('/wallet/notify', [WalletActionController::class, 'sendNotification'])->name('wallet.action.notify');
        Route::post('/save-firebase-token', [FirebaseTokenController::class, 'store'])->name('firebase.token.store');

        
        Route::post('/deposits/{id}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
        Route::post('/deposits/{id}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
        Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
        
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/withdrawals/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('/withdrawals/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
        Route::post('/withdrawals/update', [WithdrawalController::class, 'update'])->name('withdrawals.update');
    
    });
    
    // Customer Service Routes
    Route::get('/tickets', [CustomerServiceController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [CustomerServiceController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [CustomerServiceController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{id}', [CustomerServiceController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{id}/message', [CustomerServiceController::class, 'storeMessage'])->name('tickets.message.store');
    Route::post('/tickets/{id}/close', [CustomerServiceController::class, 'close'])->name('tickets.close');
    
    //Batch Action
    Route::get('/batch-create-users', [BatchUserController::class, 'showBatchCreateForm'])->name('batch.create.form');
    Route::post('/batch-create-users', [BatchUserController::class, 'createBatchUsers'])->name('batch.create.users');
    
    //Lobby
    Route::get('/lobby', [LobbyController::class, 'index'])->name('lobby.index');
    Route::post('/lobby/assign-robots', [LobbyController::class, 'assignRobots'])->name('lobby.assign-robots');
    Route::post('/lobby/detach-robot', [LobbyController::class, 'detachRobot'])->name('lobby.detach-robot');
    Route::get('/lobby/robotchat', [LobbyController::class, 'robotChat'])->name('lobby.robotchat');
    Route::put('/robot/update', [RobotController::class, 'update'])->name('robot.update');
    Route::post('/support/impersonate/{robotId}', [SupportController::class, 'impersonate'])->name('support.impersonate');
    Route::post('/support/revert-impersonation', [SupportController::class, 'revertImpersonation'])->name('support.revertImpersonation');
    Route::get('/robot/dashboard', [RobotController::class, 'dashboard'])->name('robot.dashboard');
    Route::get('/robot/conversations/{conversation}/messages', [RobotController::class, 'getMessages'])->name('robot.conversations.messages');
    Route::post('/robot/send-message', [RobotController::class, 'sendMessage'])->name('robot.send_message');
    Route::get('/robot/friendlist', [FriendController::class, 'viewFriendList'])->name('friendlist.view');
    Route::get('/robot/incoming-requests', [RobotController::class, 'getIncomingRequests'])->name('robot.incomingRequests');
    Route::post('/robot/update-friend-status/{id}', [RobotController::class, 'updateFriendStatus'])->name('robot.updateFriendStatus');
    Route::get('/robot/sorted-chats', [RobotController::class, 'getSortedChats'])->name('robot.sortedChats');
    Route::get('/robot/search-users', [RobotController::class, 'searchUsers'])->name('robot.searchUsers');
    Route::post('/grpchat/{grpchat}/remove', [RobotController::class, 'removeConversation'])->name('grpchat.remove');
    Route::post('/conversation/{chatId}/remove', [RobotController::class, 'removeConversation'])->name('conversation.remove');
    Route::post('/categories', [RobotController::class, 'categoryStore'])->name('robot.addcategory');
    Route::post('/categories/{id}/add-member', [RobotController::class, 'addMember']);
    Route::get('/fetchCategories', [RobotController::class, 'fetchCategories']);

    
    Route::post('/robot/send-file', [RobotController::class, 'sendFile'])->name('robot.sendFile');
    Route::post('/robot/grpchats/send-file', [RobotController::class, 'sendGroupFile'])->name('robot.sendGroupFile');
    Route::post('/robot/send-audio', [RobotController::class, 'sendAudio']);
    Route::post('/robot/grpchats/send-audio', [RobotController::class, 'sendGroupAudio']);
    Route::post('/robot/grpchats/{id}/quit', [RobotController::class, 'quitGrpChat']);
    
    Route::post('/robot/add-friend', [FriendController::class, 'addFriend'])->name('robot.addFriend');
    Route::post('/robot/search-friend', [FriendController::class, 'searchFriend'])->name('robot.searchFriend');
    Route::post('/robot/friendlist/{id}/update-status', [FriendController::class, 'updateFriendStatus']);

    Route::post('/robot/recall', [RobotController::class, 'recallMessage'])->name('robot.recall');
    Route::post('/robot/remark', [RobotController::class, 'remarkUpdate'])->name('robot.remark');
    Route::post('/robot/my-qr', [RobotController::class, 'myqrcode'])->name('robot.myqrcode');
    Route::get('/robot/view-categories', [RobotController::class, 'viewCategories'])->name('robot.view-categories');

    //Grpchat
    Route::post('/robot/grpchats/create', [GrpchatController::class, 'createGrpChat'])->name('grpchats.create');
    Route::get('/grpchat/{grpchat}/settings', [GrpchatController::class, 'settings']);
    Route::post('/grpchat/settings', [GrpchatController::class, 'updateSettings']);
    Route::post('/grpchat/{grpchat}/remove-member', [GrpchatController::class, 'removeMember'])->name('grpchats.removeMember');
    Route::post('/grpchat/{grpchat}/add-member', [GrpchatController::class, 'addMember'])->name('grpchats.add-member');
    Route::get('/grpchat/{grpchat}/available-members', [GrpchatController::class, 'getAvailableMembers'])->name('grpchats.available-members');
    Route::get('/grpchat/{grpchatId}/announcement', [GrpchatController::class, 'getAnnouncement']);
    Route::post('/grpchat/{grpchatId}/add-admin', [GrpchatController::class, 'addAdmin'])->name('grpchat.addAdmin');
    Route::post('/grpchat/{grpchatId}/remove-admin', [GrpchatController::class, 'removeAdmin'])->name('grpchat.removeAdmin');
    Route::get('/grpchat/{grpchatId}/admins', [GrpchatController::class, 'getAdmins']);
    Route::get('/grpchat/{grpchatId}/members', [GrpchatController::class, 'getMembers']);
    Route::get('/grpchat/{grpchatId}/get-members', [GrpchatController::class, 'getMemberList']);

    Route::post('/grpchat/settings/mute-members', [GrpchatController::class, 'updateMuteMembers']);

    Route::post('/robot/grpchats/{grpchat}/quit', [GrpchatController::class, 'quitGrpChat'])->name('grpchats.quit');
    Route::post('/robot/grpchats/{grpchat}/messages', [GrpchatController::class, 'sendMessage'])->name('grpchats.sendMessage');
    Route::get('/robot/grpchats/{grpchat}/messages', [GrpchatController::class, 'getMessages'])->name('grpchats.getMessages');
    Route::get('/robot/friends/list', [GrpchatController::class, 'getFriendList'])->name('friends.list');
    Route::get('/robot/grpchats', [GrpchatController::class, 'showGroupChats'])->name('grpchats.index');
    
    Route::get('/robot/grpchats/{grpchat}/history', [GrpchatController::class, 'getHistory'])->name('grpchats.history');

    Route::get('/transaction', [TransactionController::class, 'index'])->name('transaction.index');
});
