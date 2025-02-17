<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RobotLink;
use Illuminate\Support\Facades\Log;

class LobbyController extends Controller
{
    public function index()
    {
        // Retrieve all support staff with their currently assigned robots
        $supports = User::whereIn('role', ['support', 'superadmin'])
            ->with(['supportedRobots' => function ($query) {
                $query->where('status', 1);
            }, 'supportedRobots.robot'])
            ->get();
    
        // Retrieve robots that are not assigned to any support staff
        $robots = User::where('role', 'client')
            ->where('robot', 1)
            ->whereDoesntHave('assignedSupport', function ($query) {
                $query->where('status', 1); // Exclude robots with active assignments
            })
            ->get();
    
        return view('pages.app.lobby.index', compact('supports', 'robots'));
    }

    public function assignRobots(Request $request)
    {
        $validated = $request->validate([
            'support_id' => 'required|exists:users,id',
            'robot_ids' => 'required|array',
            'robot_ids.*' => 'exists:users,id',
        ]);

        $supportId = $validated['support_id'];
        $robotIds = $validated['robot_ids'];

        foreach ($robotIds as $robotId) {
            // Check if the robot is already assigned to another support
            $existingLink = RobotLink::where('robot_id', $robotId)
                ->where('status', 1)
                ->where('support_id', '!=', $supportId)
                ->first();

            if ($existingLink) {
                return response()->json([
                    'message' => 'Robot already assigned to another support staff.',
                    'robot_id' => $robotId
                ], 400);
            }

            // Assign or update the robot assignment
            RobotLink::updateOrCreate(
                ['support_id' => $supportId, 'robot_id' => $robotId],
                ['status' => 1]
            );
            
            $newRobotCount = RobotLink::where('support_id', $supportId)
                ->where('status', 1)
                ->count();
        }

        return response()->json([
            'message' => 'Robots assigned successfully.',
            'newRobotCount' => $newRobotCount,
        ], 200);
    }

    public function detachRobot(Request $request)
    {
        $validated = $request->validate([
            'support_id' => 'required|exists:users,id',
            'robot_id' => 'required|exists:users,id',
        ]);

        $supportId = $validated['support_id'];
        $robotId = $validated['robot_id'];

        $robotLink = RobotLink::where('support_id', $supportId)
            ->where('robot_id', $robotId)
            ->first();

        if ($robotLink) {
            $robotLink->update(['status' => 0]);

            $newRobotCount = RobotLink::where('support_id', $supportId)
                ->where('status', 1)
                ->count();
    
            return response()->json([
                'message' => 'Robot detached successfully.',
                'newRobotCount' => $newRobotCount,
            ], 200);
            
        } else {
            Log::channel('admin')->warning("Failed to detach robot. Robot link not found.", [
                'support_id' => $supportId,
                'robot_id' => $robotId,
            ]);
            return response()->json(['message' => 'Robot link not found.'], 404);
        }
    }

    public function robotChat()
    {
        if (!in_array(auth()->user()->role, ['support', 'superadmin'])) {
            Log::channel('admin')->error("Unauthorized attempt to access robot chat.", [
                'user_id' => auth()->id(),
            ]);
            abort(403, 'Unauthorized action.');
        }
    
        $robotLinks = RobotLink::where('support_id', auth()->id())
            ->where('status', 1)
            ->with('robot')
            ->get();
    
        $robots = $robotLinks->pluck('robot');
        $userId = auth()->id(); // Capture the authenticated user's ID
    
        return view('pages.app.lobby.robotchat', compact('robots', 'userId')); // Pass it to the view
    }

}
