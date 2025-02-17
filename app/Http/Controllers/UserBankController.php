<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Userbank;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class UserBankController extends Controller
{

    public function checkBank()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => '用户未认证'], 401);
        }

        $banks = $user->userbanks()->where('status', 1)->get();

        return response()->json($banks, 200);
    }

    public function addBank(Request $request)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }
    
        $validatedData = $request->validate([
            'bankname' => 'required|string|max:255',
            'accname' => 'required|string|max:255',
            'bankno' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);
    
        $validatedData['status'] = $validatedData['status'] ?? 1; // Active by default
    
        // Check if the user already has a bank record
        $existingBank = Userbank::where('user_id', $user->id)->first();
    
        if ($existingBank) {
            // Update the existing bank record
            $existingBank->update($validatedData);
            return response()->json([
                'success' => true,
                'message' => '银行信息已更新',
                'bank' => $existingBank
            ], 200);
        } else {
            // Create a new bank record
            $validatedData['user_id'] = $user->id;
            $bank = Userbank::create($validatedData);
            return response()->json([
                'success' => true,
                'message' => '银行添加成功',
                'bank' => $bank
            ], 201);
        }
    }


}