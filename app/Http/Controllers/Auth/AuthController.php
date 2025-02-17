<?php

namespace App\Http\Controllers\Auth;

use App\Models\LoginActivity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Show the login page
    public function showLoginForm()
    {
        Log::channel('admin')->info('Login form displayed.');
        return view('pages.authentication.login');
    }

    // Handle the login request
    public function login(Request $request)
    {
        Log::channel('admin')->info('Login attempt.', ['username' => $request->username, 'ip' => $request->ip()]);
        
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);
        
        if (Auth::attempt(['name' => $request->username, 'password' => $request->password])) {
            // Record successful login activity
            LoginActivity::create([
                'user_id' => Auth::id(),
                'status' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'logged_at' => now(),
            ]);
        
            Log::channel('admin')->info('Login successful.', ['username' => $request->username]);
            return redirect()->intended('dashboard');
        }
        
        // Record failed login attempt
        LoginActivity::create([
            'user_id' => null,
            'status' => 'failed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_at' => now(),
        ]);
        
        Log::channel('admin')->warning('Login failed.', ['username' => $request->username]);
        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('username'));
    }

    // Logout function
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            LoginActivity::create([
                'user_id' => $user->id,
                'status' => 'logout',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'logged_at' => now(),
            ]);
    
            Log::channel('admin')->info('User logged out.', ['email' => $user->email]);
        }
    
        Auth::logout();
        return redirect('/login');
    }

    // Handle API login
    public function apiLogin(Request $request)
    {
        Log::channel('api')->info('API login attempt.', ['name' => $request->name, 'ip' => $request->ip()]);
    
        $request->validate([
            'name' => 'required|string',
            'password' => 'required',
        ]);
    
        if (Auth::attempt(['name' => $request->name, 'password' => $request->password])) {
            $user = Auth::user();
            Log::channel('api')->info('API login successful.', ['name' => $user->name]);
    
            // If Sanctum is used, return a token
            return response()->json([
                'status' => 'success',
                'token' => $user->createToken('API Token')->plainTextToken,
                'user' => $user,
            ]);
        }
    
        Log::channel('api')->warning('API login failed.', ['name' => $request->name]);
        return response()->json([
            'status' => 'error',
            'message' => '用户名或密码错误',
        ], 401);
    }

    public function apiRegister(Request $request)
    {
        Log::channel('admin')->info('API registration attempt.', [
            'name' => $request->name,
            'referral' => $request->referral,
            'input' => $request->all(),
        ]);
    
        try {
            // Validate the request
            $request->validate([
                'name' => 'required|string|max:255|unique:users,name|regex:/^[a-zA-Z0-9\s]+$/',
                'realname' => 'required|string|max:255',
                'password' => 'required|min:6|confirmed',
                'security_pin' => 'required|digits:6',
                'referral' => ['required', 'exists:users,referral_link'],
                'birthday' => 'nullable|date',
                'age' => 'nullable',
            ], [
                'referral.exists' => '推荐码无效，请检查并重试。',
            ]);
    
            // Retrieve the upline user ID using the referral code
            $uplineUser = \App\Models\User::where('referral_link', $request->referral)->first();
    
            if (!$uplineUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => '推荐码无效，请检查并重试。',
                ], 400);
            }
    
            // Generate a unique referral link
            do {
                $referralLink = mt_rand(100000, 999999);
            } while (\App\Models\User::where('referral_link', $referralLink)->exists());
    
            // Generate random email
            $email = $request->name . '@gmail.com';
    
            // Create the user
            $hashedPassword = Hash::make($request->password);
            $realPassword = $request->password;
    
            $user = \App\Models\User::create([
                'name' => $request->name,
                'realname' => $request->realname,
                'email' => $email,
                'password' => $hashedPassword,
                'realpass' => $realPassword, // Save the plain-text password
                'security_pin' => $request->security_pin,
                'referral' => $uplineUser->id,
                'referral_link' => $referralLink,
                'birthday' => $request->birthday,
                'age' => $request->age,
            ]);
    
            // Create a wallet for the user
            \App\Models\Wallet::create([
                'user_id' => $user->id,
                'amount' => 0,
                'freeze' => 3210000.00,
                'currency' => 'rmb',
                'type' => '正常',
                'status' => 1,
            ]);
    
            Log::channel('admin')->info('User registered successfully.', [
                'name' => $user->name,
                'referral_link' => $user->referral_link,
            ]);
    
            // Add the relationship to the friendlist table
            \App\Models\Friendlist::create([
                'user_id' => $user->id,
                'friend_id' => $uplineUser->id,
                'status' => 2, // Automatically accept the relationship
            ]);
    
            // Initiate a chat between the new user and the referred user
            $conversation = \App\Models\Conversation::firstOrCreate(
                [
                    'name' => $user->id,
                    'target' => $uplineUser->id,
                ],
                [
                    'name' => $uplineUser->id,
                    'target' => $user->id,
                ]
            );
    
            // Add the initial message
            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'message' => '我们已经成为好友，可以开始聊天啦！',
            ]);
    
            Log::channel('admin')->info('Conversation created.', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'referral_id' => $uplineUser->id,
            ]);
    
            // Automatically log in the user and generate a token
            Auth::login($user);
            $token = $user->createToken('API Token')->plainTextToken;
    
            // Fetch the upline's name
            $uplineName = $uplineUser->name;
    
            // Fetch the last message date
            $lastMessage = $conversation->messages()->latest()->first();
            $lastMessageDate = $lastMessage ? $lastMessage->created_at->toDateTimeString() : '';
    
            return response()->json([
                'status' => 'success',
                'message' => 'User registered and logged in successfully.',
                'user' => $user,
                'token' => $token,
                'conversation_id' => $conversation->id,
                'upline_name' => $uplineName,
                'lastMessageDate' => $lastMessageDate,
            ]);
        } catch (ValidationException $e) {
            Log::channel('admin')->error('Validation failed.', ['errors' => $e->errors()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::channel('admin')->error('API registration error.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
