<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\RobotLink;

class SupportController extends Controller
{
    public function impersonate($robotId)
    {
        // Ensure the user has an authorized role
        if (!in_array(auth()->user()->role, ['support', 'superadmin'])) {
            Log::channel('robot')->warning('Unauthorized impersonation attempt', [
                'user_id' => auth()->id(),
                'attempted_robot_id' => $robotId,
            ]);
            abort(403, 'Unauthorized action.');
        }

        // Generate a unique token
        $token = bin2hex(random_bytes(16));

        if ($robotId == auth()->id()) {
            // **Self-Impersonation Case**
            Log::channel('robot')->info('Support user impersonated themselves', [
                'user_id' => auth()->id(),
            ]);

            // Store the impersonation data in the session with an 'is_self' flag
            session()->put("impersonation_{$token}", [
                'support_id' => auth()->id(),
                'robot_id' => $robotId,
                'is_self' => true,
            ]);
        } else {
            // **Robot Impersonation Case**
            // Check if the robot client is assigned to the support user
            $robotLink = RobotLink::where('support_id', auth()->id())
                ->where('robot_id', $robotId)
                ->where('status', 1)
                ->first();

            if (!$robotLink) {
                Log::channel('robot')->warning('Impersonation failed: Robot not assigned', [
                    'support_id' => auth()->id(),
                    'robot_id' => $robotId,
                ]);
                abort(403, 'You are not authorized to impersonate this user.');
            }

            // Store the impersonation data in the session without the 'is_self' flag
            session()->put("impersonation_{$token}", [
                'support_id' => auth()->id(),
                'robot_id' => $robotId,
                'is_self' => false,
            ]);

            Log::channel('robot')->info('Support user impersonated robot client', [
                'support_id' => auth()->id(),
                'robot_id' => $robotId,
                'impersonation_token' => $token,
            ]);
        }

        // **Redirect to the robot.dashboard route with the impersonation token**
        return redirect()->route('robot.dashboard', ['impersonation_token' => $token]);
    }

    public function revertImpersonation()
    {
        if (session()->has('impersonated_by')) {
            // Log in back as the support user
            $supportId = session('impersonated_by');
            Auth::loginUsingId($supportId);

            // Remove the session variable
            session()->forget('impersonated_by');

            Log::channel('robot')->info('Support user reverted impersonation', [
                'support_id' => $supportId,
            ]);

            return redirect()->route('lobby.robotchat')->with('success', 'You have reverted to your account.');
        } else {
            Log::channel('robot')->warning('Revert impersonation failed: No impersonation session found', [
                'user_id' => auth()->id(),
            ]);
            abort(403, 'You are not impersonating any user.');
        }
    }
}
