<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Gateway;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    public function index()
    {
        try {
            Log::info('Fetching all deposits for admin view.');

            $deposits = Deposit::with('user')->orderBy('created_at', 'desc')->get();

            Log::info('Fetched deposits successfully.', ['deposit_count' => $deposits->count()]);

            return view('pages.app.deposit', [
                'deposits' => $deposits,
                'title' => 'User Profile',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching deposits.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch deposits'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Attempting to create a new deposit.', ['request_data' => $request->all()]);

            $user = Auth::user();
            if (!$user) {
                Log::warning('Unauthorized access attempt to create deposit.');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'gateway_id' => 'required|integer|exists:gateways,id',
            ]);

            $gateway = Gateway::find($request->gateway_id);

            if (!$gateway || $gateway->status !== 1) {
                Log::warning('Invalid or inactive gateway selected.', ['gateway_id' => $request->gateway_id]);
                return response()->json(['message' => 'Invalid or inactive gateway selected'], 422);
            }

            $txid = 'dp-' . Str::random(6);

            $deposit = Deposit::create([
                'txid' => $txid,
                'user_id' => $user->id,
                'amount' => $request->amount,
                'currency' => 'rmb',
                'status' => 'pending',
                'bankname' => $gateway->bankname,
                'accname' => $gateway->accname,
                'accno' => $gateway->accno,
                'branch' => $gateway->branch,
                'iban' => $gateway->iban,
                'gateway_id' => $gateway->id,
                'method' => $gateway->method,
            ]);

            Log::info('Deposit created successfully.', ['deposit_id' => $deposit->id]);

            return response()->json([
                'message' => 'Deposit created successfully',
                'data' => $deposit,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating deposit.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to create deposit'], 500);
        }
    }

    public function approve($id)
    {
        DB::beginTransaction();
        try {
            Log::info('Approving deposit.', ['deposit_id' => $id]);
    
            $deposit = Deposit::find($id);
    
            if (!$deposit) {
                Log::warning('Deposit not found.', ['deposit_id' => $id]);
                DB::rollBack();
                return redirect()->back()->with('error', 'Deposit not found.');
            }
    
            if ($deposit->status !== 'pending') {
                Log::warning('Deposit is not in a pending state.', [
                    'deposit_id' => $id,
                    'status' => $deposit->status
                ]);
                DB::rollBack();
                return redirect()->back()->with('error', 'Deposit is not in a pending state.');
            }
    
            // Update the deposit status to 'complete'
            $deposit->update(['status' => 'complete']);
    
            // ======= Commented Out: Create a corresponding transaction =======
            /*
            Transaction::create([
                'txid' => $deposit->txid,
                'user_id' => $deposit->user_id,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency,
                'status' => 1, // Active transaction
                'type' => 'deposit',
                'method' => '+',
            ]);
            */
    
            // ======= Commented Out: Update or Create Wallet =======
            /*
            Wallet::updateOrCreate(
                ['user_id' => $deposit->user_id], // Search by user_id only
                [
                    'currency' => $deposit->currency,
                    'status' => 1,
                    'amount' => DB::raw('amount + ' . $deposit->amount),
                    'freeze' => 0.00,
                ]
            );
            */
    
            Log::info('Deposit approved successfully.', ['deposit_id' => $id]);
    
            DB::commit();
            return redirect()->back()->with('success', 'Deposit approved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving deposit.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deposit_id' => $id,
            ]);
            return redirect()->back()->with('error', 'Failed to approve deposit.');
        }
    }

    public function reject($id)
    {
        try {
            Log::info('Rejecting deposit.', ['deposit_id' => $id]);

            $deposit = Deposit::find($id);

            if (!$deposit) {
                Log::warning('Deposit not found.', ['deposit_id' => $id]);
                return redirect()->back()->with('error', 'Deposit not found.');
            }

            if ($deposit->status !== 'pending') {
                Log::warning('Deposit is not in a pending state.', ['deposit_id' => $id, 'status' => $deposit->status]);
                return redirect()->back()->with('error', 'Deposit is not in a pending state.');
            }

            $deposit->update(['status' => 'rejected']);

            Log::info('Deposit rejected successfully.', ['deposit_id' => $id]);

            return redirect()->back()->with('success', 'Deposit rejected successfully.');
        } catch (\Exception $e) {
            Log::error('Error rejecting deposit.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deposit_id' => $id,
            ]);
            return redirect()->back()->with('error', 'Failed to reject deposit.');
        }
    }
}