<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Import the Log facade

class WalletController extends Controller
{
    public function getBalance($user_id)
    {
        Log::info("Fetching wallet balances for user_id: {$user_id}"); // Log the start of the request

        // Fetch the wallet with balance type for the given user
        $wallet = Wallet::where('user_id', $user_id)
            ->first();

        if (!$wallet) {
            Log::error("Wallet not found for user_id: {$user_id}");
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        Log::info("Wallet found for user_id: {$user_id}, amount: {$wallet->amount}, freeze: {$wallet->freeze}, status: {$wallet->status}");

        return response()->json([
            'user_id' => $wallet->user_id,
            'balance' => $wallet->amount, // Available balance
            'frozen_balance' => $wallet->freeze, // Frozen balance
            'currency' => $wallet->currency,
            'type' => $wallet->type,
            'status' => $wallet->status,
        ], 200);
    }
}
