<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\FirebaseToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class WalletActionController extends Controller
{
    public function index()
    {
        Log::info('Index method called.');
    
        // Retrieve users who have a record in the wallets table
        $users = User::select('id', 'name', 'referral_link', 'realname')
            ->whereExists(function ($query) {
                $query->select('user_id')
                      ->from('wallets')
                      ->whereColumn('wallets.user_id', 'users.id');
            })
            ->get();
    
        return view('pages.app.walletaction', [
            'title' => '钱包操作',
            'users' => $users,
        ]);
    }
    
    public function deposit(Request $request)
    {
        Log::info('Deposit method called.');
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:+,-',
        ]);

        Log::info('Validation passed for deposit.', ['user_id' => $request->user_id, 'amount' => $request->amount, 'method' => $request->method]);

        $wallet = Wallet::where('user_id', $request->user_id)
            ->firstOrFail();

        Log::info('Wallet retrieved for user.', ['wallet_id' => $wallet->id]);

        $wallet->amount += ($request->method === '+') ? $request->amount : -$request->amount;
        $wallet->save();

        Log::info('Wallet updated.', ['new_amount' => $wallet->amount]);

        Transaction::create([
            'txid' => 'dp' . Str::random(8),
            'user_id' => $request->user_id,
            'currency' => 'rmb',
            'amount' => $request->amount,
            'status' => 1,
            'type' => 'deposit',
            'method' => $request->method,
        ]);

        Log::info('Transaction record created for deposit.', ['user_id' => $request->user_id, 'amount' => $request->amount]);

        return redirect()->back()->with('success', '钱包更新成功。');
    }

    public function freeze(Request $request)
    {
        Log::info('Freeze method called.');
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:+,-',
        ]);

        Log::info('Validation passed for freeze.', ['user_id' => $request->user_id, 'amount' => $request->amount, 'method' => $request->method]);

        $wallet = Wallet::where('user_id', $request->user_id)
            ->firstOrFail();

        Log::info('Wallet retrieved for freeze.', ['wallet_id' => $wallet->id]);

        $wallet->freeze += ($request->method === '+') ? $request->amount : -$request->amount;
        $wallet->save();

        Log::info('Wallet freeze updated.', ['new_freeze' => $wallet->freeze]);

        Transaction::create([
            'txid' => 'fr' . Str::random(8),
            'user_id' => $request->user_id,
            'currency' => 'rmb',
            'amount' => $request->amount,
            'status' => 1,
            'type' => 'freeze',
            'method' => $request->method,
        ]);

        Log::info('Transaction record created for freeze.', ['user_id' => $request->user_id, 'amount' => $request->amount]);

        return redirect()->back()->with('success', '冻结更新成功。');
    }

    public function adjustWallet(Request $request)
    {
        Log::info('adjustWallet method called.');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:+,-',
        ]);

        Log::info('Validation passed for adjustWallet.', ['user_id' => $request->user_id, 'amount' => $request->amount, 'method' => $request->method]);

        $wallet = Wallet::where('user_id', $request->user_id)
            ->firstOrFail();

        Log::info('Wallet retrieved for adjustWallet.', ['wallet_id' => $wallet->id]);

        $wallet->amount += ($request->method === '+') ? $request->amount : -$request->amount;
        $wallet->save();

        Log::info('Wallet amount adjusted.', ['new_amount' => $wallet->amount]);

        Transaction::create([
            'txid' => 'ad' . Str::random(8),
            'user_id' => $request->user_id,
            'currency' => 'rmb',
            'amount' => $request->amount,
            'status' => 1,
            'type' => 'adjust',
            'method' => $request->method,
        ]);

        Log::info('Transaction record created for adjustWallet.', ['user_id' => $request->user_id, 'amount' => $request->amount]);

        return redirect()->back()->with('success', '钱包调整成功。');
    }
    
    public function sendNotification(Request $request)
    {
        try {
            Log::info('Starting to send notifications.');

            $firebase = (new Factory)
                ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                ->createMessaging();

            $tokens = FirebaseToken::pluck('token')->toArray();

            Log::info('Fetched Firebase tokens.', ['token_count' => count($tokens)]);

            if (empty($tokens)) {
                Log::warning('No Firebase tokens found.');
                return redirect()->back()->with('error', '未找到设备令牌。');
            }

            $notification = [
                'title' => '按钮点击',
                'body' => '钱包操作页面中的按钮已被点击！',
            ];

            foreach ($tokens as $token) {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification);

                $firebase->send($message);
                Log::info('Notification sent to token.', ['token' => $token]);
            }

            Log::info('All notifications sent successfully.');

            return redirect()->back()->with('success', '推送通知发送成功！');
        } catch (\Exception $e) {
            Log::error('Failed to send notifications.', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', '发送通知失败: ' . $e->getMessage());
        }
    }
    
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            Log::warning('Validation failed.', ['errors' => $exception->errors()]);
        }

        return parent::render($request, $exception);
    }
}
