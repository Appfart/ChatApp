<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function getUserTransactions($user_id)
{
    try {
        // Fetch deposits for the user
        $deposits = Deposit::where('user_id', $user_id)
            ->select('id', 'amount', 'status', 'created_at', DB::raw("'deposit' as type"))
            ->get();

        // Fetch withdrawals for the user
        $withdrawals = Withdrawal::where('user_id', $user_id)
            ->select('id', 'amount', 'status', 'method', 'created_at', DB::raw("'withdrawal' as type"))
            ->get();

        // Combine deposits and withdrawals into a single collection
        $transactions = $deposits->merge($withdrawals)->sortByDesc('created_at')->values();

        // Log the fetched transactions
        Log::info('Fetched User Transactions', [
            'user_id' => $user_id,
            'transactions' => $transactions->toArray(),
        ]);

        return response()->json($transactions, 200);
    } catch (\Exception $e) {
        // Log the error details
        Log::error('Error fetching user transactions', [
            'user_id' => $user_id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => '服务器错误，请稍后再试。',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    
    public function index()
    {
        // Fetch transactions with related user, ordered by creation date descending
        $transactions = Transaction::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15); // Adjust pagination as needed

        // Pass a title to the view for the page title slot
        $title = '全用户明细';

        return view('pages.app.transactions.index', compact('transactions', 'title'));
    }

}
