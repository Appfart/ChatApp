<?php

namespace App\Http\Controllers;

use App\Models\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayController extends Controller
{
    public function index()
    {
        try {
            // Log the start of the request
            Log::info('Fetching active gateways');

            // Fetch gateways where status is active
            $gateways = Gateway::where('status', 1)->get();

            // Log the fetched data
            Log::info('Active gateways fetched successfully', ['gateways' => $gateways]);

            return response()->json($gateways, 200);
        } catch (\Exception $e) {
            // Log any exceptions
            Log::error('Error fetching gateways', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to fetch gateways'], 500);
        }
    }
}