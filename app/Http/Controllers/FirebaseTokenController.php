<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FirebaseToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FirebaseTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Update existing token or create a new one
            FirebaseToken::updateOrCreate(
                ['token' => $request->token],
                ['user_id' => $user->id]
            );

            Log::info('Firebase token saved successfully for user ID: ' . $user->id);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Firebase token: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to save token'], 500);
        }
    }
}