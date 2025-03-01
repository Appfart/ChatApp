<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\LoginActivity;
use App\Models\Userbank;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    
    public function create()
    {
        // Log the retrieval of users with specific roles
        Log::info('Fetching users with roles: superadmin and support');
        
        $users = User::select('id', 'name', 'referral_link', 'realname')
            ->whereIn('role', ['superadmin', 'support'])
            ->get();
    
        Log::info('Fetched users for referral dropdown', ['users_count' => $users->count()]);
    
        return view('pages.app.user.create', [
            'title' => 'Batch Create Users',
            'users' => $users,
        ]);
    }
    
    public function store(Request $request)
    {
        Log::info('Batch user creation started', [
            'input' => $request->all()
        ]);
    
        try {
            Log::info('Validating request data', ['input' => $request->all()]);
            $request->validate([
                'user_count' => 'required|integer|min:1',
                'password' => 'required|string',
                'security_pin' => 'required|digits:6',
                'referral' => 'required|exists:users,id',
            ]);
    
            $userCount = $request->input('user_count');
            $defaultPassword = $request->input('password');
            $securityPin = $request->input('security_pin');
            $referral = $request->input('referral');
    
            Log::info('Validation passed, starting user creation', ['referral_id' => $referral]);
    
            $users = [];
            for ($i = 0; $i < $userCount; $i++) {
                Log::info('Creating user', ['iteration' => $i + 1]);
    
                $randomName = 'Support_' . rand(100, 999);
                while (User::where('name', $randomName)->exists()) {
                    Log::info('Random name exists, regenerating', ['name' => $randomName]);
                    $randomName = 'Support_' . rand(100, 999);
                }
    
                $referralLink = rand(100000, 999999);
                while (User::where('referral_link', $referralLink)->exists()) {
                    Log::info('Referral link exists, regenerating', ['referral_link' => $referralLink]);
                    $referralLink = rand(100000, 999999);
                }
    
                $user = User::create([
                    'name' => $randomName,
                    'realname' => $randomName,
                    'email' => $randomName . '@gmail.com',
                    'password' => bcrypt($defaultPassword),
                    'realpass' => $defaultPassword,
                    'security_pin' => $securityPin,
                    'role' => 'support',
                    'referral' => $referral,
                    'referral_link' => $referralLink,
                    'robot' => 0,
                    'birthday' => now()->subYears(rand(20, 70))->format('Y-m-d'),
                    'age' => rand(20, 70),
                ]);
    
                Log::info('User created successfully', ['name' => $user->name, 'email' => $user->email]);
                $users[] = $user;
            }
    
            Log::info('Batch user creation completed', ['total_users_created' => count($users)]);
    
            return redirect()->route('user.list')->with('success', count($users) . ' users created successfully!');
        } catch (\Exception $e) {
            Log::error('An error occurred during batch user creation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return redirect()->back()->with('error', 'An error occurred during user creation.');
        }
    }

    public function profile()
    {
        $user = Auth::user();
        $loginActivities = LoginActivity::where('user_id', $user->id)->latest()->get();
    
        return view('pages.app.user.profile', [
            'title' => 'User Profile',
            'user' => $user,
            'loginActivities' => $loginActivities,
        ]);
    }
    
    public function flutterprofile()
    {
        $user = Auth::user();
        $loginActivities = LoginActivity::where('user_id', $user->id)->latest()->get();

        // Assuming the User model has an 'avatar' attribute
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar, // Ensure this contains the correct path or URL
                    // Add other necessary fields
                ],
                'loginActivities' => $loginActivities,
            ],
        ]);
    }
    
    public function updateProfileWeb(Request $request)
    {
        $user = auth()->user();
    
        Log::info('Received updateProfileWeb request', [
            'has_avatar' => $request->hasFile('avatar'),
            'avatar_present' => $request->file('avatar') ? 'Yes' : 'No',
            'files' => $request->allFiles(),
            'all_input' => $request->all(),
        ]);
    
        $request->validate([
            'name' => 'required|string|max:255|unique:users,name,' . $user->id . '|regex:/^[a-zA-Z0-9]+$/',
            'realname' => 'required|string|max:255',
            'age' => 'required|integer|min:0',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
                Log::info('Old avatar deleted', ['user_id' => $user->id, 'avatar_path' => $user->avatar]);
            }
    
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            Log::info('Avatar updated', ['user_id' => $user->id, 'new_avatar_path' => $path]);
        }
    
        // Update user information
        $user->name = $request->name;
        $user->realname = $request->realname;
        $user->age = $request->age;
        $user->save();
    
        Log::info('User profile updated', [
            'user_id' => $user->id,
            'name' => $user->name,
            'realname' => $user->realname,
            'age' => $user->age,
        ]);
    
        return redirect()->back()->with('success', '个人信息已更新。');
    }
    
    public function updateProfileRobot(Request $request)
    {
        $request->validate([
            'robot_id' => 'required|exists:users,id',
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name' => 'nullable|string|max:255|unique:users,name,' . $request->robot_id . '|regex:/^[a-zA-Z0-9]+$/',
        ]);
    
        // Fetch the user (robot) using the provided ID
        $robot = User::findOrFail($request->robot_id);
    
        // If a new name is provided, update it
        if ($request->filled('name')) {
            $robot->name = $request->name;
        }
    
        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($robot->avatar && Storage::disk('public')->exists($robot->avatar)) {
                Storage::disk('public')->delete($robot->avatar);
            }
    
            // Store the new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $robot->avatar = $path;
        }
    
        $robot->save(); // Save the updated data
    
        // Return success response
        return response()->json(['success' => true, 'message' => '头像更新成功！']);
    }
    
    public function updatePasswordWeb(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            Log::warning('Failed password update attempt - incorrect current password', [
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['current_password' => '当前密码不正确。']);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        Log::info('User password updated', ['user_id' => $user->id]);

        return redirect()->back()->with('success', '密码已更新。');
    }

    public function updateSecurityPinWeb(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_pin' => 'required|digits:6',
            'new_pin' => 'required|digits:6|confirmed',
        ]);

        // Check current PIN
        if ($request->current_pin !== $user->security_pin) {
            Log::warning('Failed security PIN update attempt - incorrect current PIN', [
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['current_pin' => '当前 PIN 不正确。']);
        }

        // Update Security PIN
        $user->security_pin = $request->new_pin;
        $user->save();

        Log::info('User security PIN updated', ['user_id' => $user->id]);

        return redirect()->back()->with('success', '安全 PIN 已更新。');
    }

    public function settings()
    {
        Log::info('User accessed account settings page.');

        // Retrieve the authenticated user
        $user = Auth::user();

        // Pass both 'title' and 'user' to the view
        return view('pages.app.user.account-settings', [
            'title' => 'Account Settings',
            'user' => $user,
        ]);
    }
    
    public function userList(Request $request)
    {
        Log::info('Fetching user list with filters', $request->all());
    
        $name = $request->input('name');
        $referral = $request->input('referral');
        $birthday = $request->input('birthday');
        $securityPin = $request->input('security_pin');
    
        $query = User::query()
            ->leftJoin('users as upline', 'users.referral', '=', 'upline.id')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id') // Join with wallets table
            ->select(
                'users.*',
                'upline.name as upline_name',
                'wallets.amount',
                'wallets.freeze',
                'wallets.status',
                'wallets.type'
            );
    
        if ($name) {
            $query->where('users.name', 'like', "%$name%");
        }
    
        if ($referral) {
            $query->where('upline.name', 'like', "%$referral%");
        }
    
        if ($birthday) {
            $query->whereDate('users.birthday', $birthday);
        }
    
        if ($securityPin) {
            $query->where('users.security_pin', $securityPin);
        }
    
        $users = $query->get();
    
        Log::info('Fetched users', ['count' => $users->count()]);
    
        return view('pages.app.userlist', [
            'title' => 'User List',
            'users' => $users,
        ]);
    }

    private function generateChatId()
    {
        $chatId = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        Log::info('Generated new chat ID', ['chat_id' => $chatId]);

        return $chatId;
    }

    private function assignSupport(array $supportStaff)
    {
        $assignedStaff = $supportStaff[array_rand($supportStaff)];

        Log::info('Assigned support staff', ['staff' => $assignedStaff]);

        return $assignedStaff;
    }
    
    //Flutter
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $users = User::select('id', 'name')->get(); // Fetch all users
        $wallet = $user->wallet;
    
        // Fetch banks directly from the userbanks table where user_id matches
        $banks = UserBank::where('user_id', $id)->get(); 
    
        return view('pages.app.user.edit', compact('user', 'users', 'wallet', 'banks'));
    }

    public function updateUser(Request $request, $id)
    {
        Log::info('Updating user details', ['user_id' => $id, 'request_data' => $request->all()]);
    
        $user = User::findOrFail($id);
    
        // Validate user fields
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255|unique:users,name,' . $id . '|regex:/^[a-zA-Z0-9]+$/',
            'realname' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:1|max:120',
            'referral_link' => 'nullable|string|max:255',
            'password' => 'nullable|string|confirmed',
            'security_pin' => 'nullable|string',
            'avatar'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ], [
            'name.regex' => '用户名格式无效，只能包含字母和数字。',
            'password.confirmed' => '两次输入的密码不一致。',
        ]);
    
        DB::beginTransaction();
        try {
            $updateData = [];
    
            // Update only if values are provided
            if ($request->filled('name')) {
                $updateData['name'] = $request->input('name');
            }
    
            if ($request->filled('realname')) {
                $updateData['realname'] = $request->input('realname');
            }
    
            if ($request->filled('age')) {
                $updateData['age'] = $request->input('age');
            }
    
            if ($request->filled('referral_link')) {
                $updateData['referral_link'] = $request->input('referral_link');
            }
    
            if ($request->filled('security_pin')) {
                $updateData['security_pin'] = $request->input('security_pin');
            }
    
            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->input('password'));
            }
            
            if ($request->hasFile('avatar')) {
                // Optionally delete the old avatar if you wish:
                // Storage::disk('public')->delete($user->avatar);
    
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $updateData['avatar'] = $avatarPath;
            }
    
            // Only update fields if there is something to update
            if (!empty($updateData)) {
                $user->update($updateData);
            }
    
            DB::commit();
            Log::info('User details updated successfully', ['user_id' => $id]);
    
            return redirect()->route('user.edit', $id)->with('success', '用户详情已成功更新！');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user details', ['user_id' => $id, 'error' => $e->getMessage()]);
            return back()->withErrors('更新用户详情时发生错误，请重试。');
        }
    }
    
    public function updateBank(Request $request, $id)
    {
        Log::info('Updating user bank details', ['user_id' => $id, 'request_data' => $request->all()]);

        $user = User::with('userbanks')->findOrFail($id);

        // Validate bank data
        $validatedData = $request->validate([
            'banks' => 'nullable|array|max:1',
            'banks.*.id' => 'nullable|integer|exists:userbanks,id',
            'banks.*.bankname' => 'required|string|max:255',
            'banks.*.accname' => 'required|string|max:255',
            'banks.*.bankno' => 'required|string|max:50',
            'banks.*.branch' => 'nullable|string|max:255',
            'banks.*.iban' => 'nullable|string|max:34',
            'banks.*.status' => 'required|in:0,1', // Changed to integer
        ], [
            'banks.max' => '每个用户只能关联一个银行。',
            'banks.*.status.in' => '银行状态必须是激活或未激活。',
        ]);

        DB::beginTransaction();
        try {
            $banks = $request->input('banks');

            if ($banks && count($banks) > 1) {
                return back()->withErrors('每个用户只能关联一个银行。');
            }

            if ($banks && count($banks) === 1) {
                $bankData = $banks[0];

                if (isset($bankData['id'])) {
                    // Update existing bank
                    $bank = Userbank::where('id', $bankData['id'])->where('user_id', $user->id)->first();
                    if ($bank) {
                        $bank->update([
                            'bankname' => $bankData['bankname'],
                            'accname' => $bankData['accname'],
                            'bankno' => $bankData['bankno'],
                            'branch' => $bankData['branch'],
                            'iban' => $bankData['iban'],
                            'status' => $bankData['status'], // Now integer
                        ]);
                    } else {
                        return back()->withErrors('未找到指定的银行。');
                    }
                } else {
                    // Ensure no existing banks
                    if ($user->userbanks()->count() > 0) {
                        return back()->withErrors('用户已关联一个银行。如需添加新银行，请先删除现有银行。');
                    }

                    // Create new bank
                    Userbank::create([
                        'user_id' => $user->id,
                        'bankname' => $bankData['bankname'],
                        'accname' => $bankData['accname'],
                        'bankno' => $bankData['bankno'],
                        'branch' => $bankData['branch'] ?? null,
                        'iban' => $bankData['iban'] ?? null,
                        'status' => $bankData['status'], // Now integer
                    ]);
                }
            } else {
                // If no banks are submitted, delete existing banks
                $user->userbanks()->delete();
            }

            DB::commit();
            Log::info('User bank details updated successfully', ['user_id' => $id]);

            return redirect()->route('user.edit', $id)->with('success', '银行详情已成功更新！');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user bank details', ['user_id' => $id, 'error' => $e->getMessage()]);
            return back()->withErrors('更新银行详情时发生错误，请重试。');
        }
    }
    
    public function updateWallet(Request $request, $id)
    {
        Log::info('Updating user wallet details', ['user_id' => $id, 'request_data' => $request->all()]);
    
        $user = User::findOrFail($id);
    
        // Validate wallet data
        $validatedData = $request->validate([
            'wallet_amount' => 'nullable|numeric|min:0',
            'wallet_freeze' => 'nullable|numeric|min:0',
            'wallet_status' => 'nullable|in:0,1',
            'wallet_type' => 'nullable|string|max:255',
        ], [
            'wallet_amount.numeric' => '钱包金额必须是数字。',
            'wallet_freeze.numeric' => '冻结金额必须是数字。',
            'wallet_status.in' => '钱包状态必须是开启或关闭。',
            'wallet_type.max' => '钱包状态显示不能超过255个字符。',
        ]);
    
        DB::beginTransaction();
        try {
            // Update or create wallet
            $walletData = [
                'amount' => $request->input('wallet_amount', 0),
                'freeze' => $request->input('wallet_freeze', 0),
                'status' => $request->input('wallet_status', 1),
                'type' => $request->input('wallet_type', '正常'),
            ];
    
            if ($user->wallet) {
                $user->wallet->update($walletData);
                Log::info('Wallet updated successfully', ['user_id' => $id]);
            } else {
                Wallet::create([
                    'user_id' => $user->id,
                    'amount' => $walletData['amount'],
                    'freeze' => $walletData['freeze'],
                    'status' => $walletData['status'],
                    'type' => $walletData['type'],
                ]);
                Log::info('Wallet created successfully', ['user_id' => $id]);
            }
    
            DB::commit();
            return redirect()->route('user.edit', $id)->with('success', '钱包详情已成功更新！');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating wallet details', ['user_id' => $id, 'error' => $e->getMessage()]);
            return back()->withErrors('更新钱包详情时发生错误，请重试。');
        }
    }

    public function resetPassword($id)
    {
        Log::info('Resetting password for user', ['user_id' => $id]);

        $user = User::findOrFail($id);
        $newPassword = str_random(8);

        $user->update(['password' => bcrypt($newPassword)]);

        Log::info('Password reset successfully', ['user_id' => $id, 'new_password' => $newPassword]);

        return response()->json([
            'success' => true,
            'password' => $newPassword,
        ]);
    }

    public function updateProfile(Request $request)
    {
        Log::info('Updating user profile', ['user_id' => $request->user()->id, 'request_data' => $request->all()]);
    
        $user = $request->user();
    
        try {
            $validatedData = $request->validate([
                'realname' => 'required|string|max:255',
                'name' => 'required|string|max:255|unique:users,name,' . $user->id . '|regex:/^[a-zA-Z0-9]+$/',
                'age' => 'required|integer|min:1|max:120',
                'avatar' => 'nullable|image|max:20480', // Removed the trailing dot
                'security_pin' => 'nullable|digits:4',
            ]);
    
            Log::info('Validation passed', ['user_id' => $user->id]);
    
        } catch (ValidationException $e) {
            Log::warning('Validation failed', [
                'user_id' => $user->id,
                'errors' => $e->errors(),
                'request_data' => $request->except(['password', 'security_pin'])
            ]);
            // Re-throw the exception to let Laravel handle the response
            throw $e;
        }
    
        $user->realname = $request->input('realname');
        $user->name = $request->input('name'); // Save login nickname
        $user->age = $request->input('age');
    
        if ($request->filled('security_pin')) {
            $user->security_pin = $request->input('security_pin');
            Log::info('Security PIN updated', ['user_id' => $user->id]);
        }
    
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::delete($user->avatar);
            }
    
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
    
            Log::info('Avatar updated', ['user_id' => $user->id, 'avatar_path' => $path]);
        }
    
        $user->save();
    
        Log::info('Profile updated successfully', ['user_id' => $user->id]);
    
        return response()->json([
            'message' => 'Profile updated successfully',
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
        ]);
    }
    
    public function initiateChat(Request $request, $id)
    {
        $authUser = Auth::user(); // Ensure Auth facade is correctly imported
    
        if ($authUser->id == $id) {
            return redirect()->back()->with('error', 'You cannot chat with yourself.');
        }
    
        $targetUser = User::findOrFail($id);
    
        Log::info('Initiating chat', [
            'auth_user_id' => $authUser->id,
            'target_user_id' => $targetUser->id,
        ]);
    
        // Check if a conversation already exists between these users
        $conversation = Conversation::where(function($query) use ($authUser, $targetUser) {
                $query->where('name', $authUser->id)
                      ->where('target', $targetUser->id);
            })->orWhere(function($query) use ($authUser, $targetUser) {
                $query->where('name', $targetUser->id)
                      ->where('target', $authUser->id);
            })->first();
    
        if (!$conversation) {
            // Create a new conversation
            $conversation = Conversation::create([
                'name' => $targetUser->id,
                'target' => $authUser->id,
            ]);
    
            Log::info('Created new conversation', [
                'conversation_id' => $conversation->id,
            ]);
    
            // Create the initial message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $authUser->id,
                'message' => '我们已经成为好友，可以开始聊天啦',
            ]);
    
            Log::info('Created initial message', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'user_id' => $authUser->id,
            ]);
        } else {
            Log::info('Conversation already exists', [
                'conversation_id' => $conversation->id,
            ]);
        }
    
        // Redirect to the 'chat' route with the conversation ID as a query parameter
        return redirect()->route('chat', ['conversation_id' => $conversation->id])
                         ->with('success', 'Chat initiated successfully!');
    }
}
