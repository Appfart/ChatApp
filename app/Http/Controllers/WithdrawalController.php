<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Userbank;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    const MAX_ERROR_ATTEMPTS = 3;
    
    public function index(Request $request)
    {
        Log::info('Fetching all withdrawals');
    
        // Optional filtering (e.g., by status or user)
        $status = $request->query('status');
        $query = Withdrawal::query();
    
        if ($status) {
            $query->where('status', $status);
        }
    
        // Include user details for display
        $withdrawals = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('pages.app.withdrawal', compact('withdrawals'));
    }
    
    public function withdraw(Request $request)
    {
        Log::info('Withdrawal request received', ['request' => $request->all()]);
    
        // 自定义验证错误消息
        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'bank_id' => 'required|integer|exists:userbanks,id',
            'security_pin' => 'required|string',
        ], [
            'amount.required' => '提现金额是必填的。',
            'amount.numeric' => '提现金额必须是数字。',
            'amount.min' => '提现金额至少为0.01。',
            'bank_id.required' => '必须绑定银行卡。',
            'bank_id.integer' => '银行卡错误。',
            'bank_id.exists' => '选择的银行不存在。',
            'security_pin.required' => '提款密码是必填的。',
            'security_pin.string' => '提款密码必须是字符串。',
        ]);
    
        Log::info('Withdrawal request validated', ['validatedData' => $validatedData]);
    
        $user = Auth::user();
    
        if (!$user) {
            Log::warning('Unauthenticated withdrawal attempt', ['user' => null]);
            return response()->json(['message' => '未认证'], 401);
        }
    
        // 检查用户是否已被锁定
        if ($user->error >= self::MAX_ERROR_ATTEMPTS) {
            Log::warning('User account locked due to too many failed attempts', ['user_id' => $user->id]);
            return response()->json(['message' => '由于多次输入错误提款密码，您的账户已被锁定。请联系管理员。'], 403);
        }
    
        if ($user->security_pin !== $validatedData['security_pin']) {
            Log::warning('Invalid security pin attempt', ['user_id' => $user->id]);
    
            // 增加错误计数
            $user->increment('error');
    
            // 检查是否达到最大错误次数
            if ($user->error >= self::MAX_ERROR_ATTEMPTS) {
                // 更新钱包状态和类型
                $wallet = Wallet::where('user_id', $user->id)->first();
    
                if ($wallet) {
                    $wallet->status = 0;
                    $wallet->type = "多次提交无效提款密码！账户提现已被冻结！请联系客服";
                    $wallet->save();
    
                    Log::info('Wallet status and type updated due to too many failed attempts', [
                        'user_id' => $user->id,
                        'wallet_id' => $wallet->id,
                        'status' => $wallet->status,
                        'type' => $wallet->type,
                    ]);
                } else {
                    Log::warning('Wallet not found for user during account lock', ['user_id' => $user->id]);
                }
    
                // 重置错误计数
                $user->error = 0;
                $user->save();
    
                Log::warning('User account locked after reaching max error attempts and error count reset', ['user_id' => $user->id]);
            }
    
            return response()->json(['message' => '提款密码无效'], 400);
        }
    
        // 重置错误计数
        if ($user->error > 0) {
            $user->error = 0;
            $user->save();
        }
    
        $wallet = Wallet::where('user_id', $user->id)->first();
    
        if (!$wallet || $wallet->amount < $validatedData['amount']) {
            Log::warning('Insufficient balance for withdrawal', [
                'user_id' => $user->id,
                'wallet_amount' => $wallet ? $wallet->amount : '未找到钱包',
                'requested_amount' => $validatedData['amount']
            ]);
            return response()->json(['message' => '余额不足'], 400);
        }
    
        $bank = Userbank::where('id', $validatedData['bank_id'])
                        ->where('user_id', $user->id)
                        ->where('status', 1)
                        ->first();
    
        if (!$bank) {
            Log::warning('Invalid bank selection', [
                'user_id' => $user->id,
                'bank_id' => $validatedData['bank_id']
            ]);
            return response()->json(['message' => '银行选择无效'], 400);
        }
    
        try {
            // 扣除用户钱包中的金额
            $wallet->amount -= $validatedData['amount'];
            $wallet->save();
            Log::info('Amount deducted from wallet', [
                'user_id' => $user->id,
                'deducted_amount' => $validatedData['amount'],
                'new_balance' => $wallet->amount
            ]);
    
            // 生成唯一的 txid（例如，wd- + 8 个随机字母数字字符）
            $txid = 'wd-' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            Log::info('Generated txid for withdrawal', ['txid' => $txid]);
    
            // 在 withdrawals 表中保存提现记录
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $validatedData['amount'],
                'currency' => 'rmb',
                'status' => 'pending',
                'gateway' => $bank->id,
                'method' => "local",
                'txid' => $txid,
                'bankname' => $bank->bankname,
                'accname' => $bank->accname,
                'accno' => $bank->bankno,
                'branch' => $bank->branch ?? '-',
            ]);
            Log::info('Withdrawal record created', ['withdrawal' => $withdrawal]);
    
            return response()->json([
                'message' => '提现请求已成功提交，待审批。',
                'txid' => $txid,
                'withdrawal' => $withdrawal,
                'balance' => $wallet->amount,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error processing withdrawal', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => '处理提现请求失败。', 'error' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request)
    {
        Log::info('Withdrawal approval request received', ['request' => $request->all()]);
        $user = Auth::user();
        
        if ($user->role !== 'superadmin') {
            abort(403, '未授权的操作。');
        }
        
        $validatedData = $request->validate([
            'txid' => 'required|string|exists:withdrawals,txid',
        ]);

        $withdrawal = Withdrawal::where('txid', $validatedData['txid'])
                                ->where('status', 'pending')
                                ->first();

        if (!$withdrawal) {
            Log::warning('Invalid or already processed withdrawal approval', ['txid' => $validatedData['txid']]);
            return response()->json(['message' => '无效或已处理的提现请求。'], 400);
        }

        \DB::beginTransaction();

        try {
            $withdrawal->status = 'complete';
            $withdrawal->save();

            Transaction::create([
                'user_id' => $withdrawal->user_id,
                'type' => 'withdrawal',
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'txid' => $withdrawal->txid,
                'status' => '1',
                'method' => '-',
            ]);

            \DB::commit();

            Log::info('Withdrawal approved successfully', ['txid' => $validatedData['txid']]);

            return response()->json([
                'message' => '提现已成功批准。',
                'balance' => Wallet::where('user_id', $withdrawal->user_id)->value('amount'),
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Error approving withdrawal', ['txid' => $validatedData['txid'], 'error' => $e->getMessage()]);
            return response()->json(['message' => '批准提现失败。', 'error' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        Log::info('Withdrawal rejection request received', ['request' => $request->all()]);
        
        $user = Auth::user();
        if ($user->role !== 'superadmin') {
            abort(403, '未授权的操作。');
        }
        
        $validatedData = $request->validate([
            'txid' => 'required|string|exists:withdrawals,txid',
            'method' => 'nullable|string|max:50',
        ]);

        $withdrawal = Withdrawal::where('txid', $validatedData['txid'])
                                ->where('status', 'pending')
                                ->first();

        if (!$withdrawal) {
            Log::warning('Invalid or already processed withdrawal rejection', ['txid' => $validatedData['txid']]);
            return response()->json(['message' => '无效或已处理的提现请求。'], 400);
        }

        \DB::beginTransaction();

        try {
            $wallet = Wallet::where('user_id', $withdrawal->user_id)->first();

            if (!$wallet) {
                Log::warning('Wallet not found for user during withdrawal rejection', ['user_id' => $withdrawal->user_id]);
                return response()->json(['message' => '未找到用户的钱包。'], 400);
            }

            $wallet->amount += $withdrawal->amount;
            $wallet->save();

            $withdrawal->status = 'rejected';
            $withdrawal->method = $validatedData['method'] ?? null;
            $withdrawal->save();

            \DB::commit();

            Log::info('Withdrawal rejected successfully', ['txid' => $validatedData['txid']]);

            return response()->json([
                'message' => '提现请求已成功拒绝，金额已退还。',
                'balance' => $wallet->amount,
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Error rejecting withdrawal', ['txid' => $validatedData['txid'], 'error' => $e->getMessage()]);
            return response()->json(['message' => '拒绝提现失败。', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        Log::info('Withdrawal update request received', ['request' => $request->all()]);
        $user = Auth::user();

        if ($user->role !== 'superadmin') {
            abort(403, '未授权的操作。');
        }

        $validatedData = $request->validate([
            'txid' => 'required|string|exists:withdrawals,txid',
            'amount' => 'required|numeric|min:0.01',
            'bankname' => 'required|string|max:255',
            'accname' => 'required|string|max:255',
            'accno' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
        ]);

        $withdrawal = Withdrawal::where('txid', $validatedData['txid'])
                                ->whereIn('status', ['pending', 'rejected'])
                                ->first();

        if (!$withdrawal) {
            Log::warning('Invalid withdrawal update attempt', ['txid' => $validatedData['txid']]);
            return response()->json(['message' => '无效的提现请求。'], 400);
        }

        try {
            $withdrawal->amount = $validatedData['amount'];
            $withdrawal->bankname = $validatedData['bankname'];
            $withdrawal->accname = $validatedData['accname'];
            $withdrawal->accno = $validatedData['accno'];
            $withdrawal->branch = $validatedData['branch'];
            $withdrawal->save();

            Log::info('Withdrawal updated successfully', ['txid' => $validatedData['txid']]);

            return response()->json([
                'message' => '提现请求已成功更新。',
                'withdrawal' => $withdrawal,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating withdrawal', ['txid' => $validatedData['txid'], 'error' => $e->getMessage()]);
            return response()->json(['message' => '更新提现请求失败。', 'error' => $e->getMessage()], 500);
        }
    }

    public function getPendingWithdrawals()
    {
        Log::info('Fetching pending/rejected withdrawals for user');

        $user = Auth::user();

        if (!$user) {
            Log::warning('Unauthenticated attempt to fetch pending withdrawals');
            return response()->json(['message' => '未认证'], 401);
        }

        $withdrawals = Withdrawal::where('user_id', $user->id)
                                 ->get();

        Log::info('Pending withdrawals fetched', ['user_id' => $user->id, 'count' => $withdrawals->count()]);

        return response()->json($withdrawals, 200);
    }
}