<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Models\LastOnline;
use Illuminate\Support\Facades\Log;

class RecordLastOnline
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (Auth::check()) {
            $user = Auth::user();
            $ipAddress = $request->ip();

            LastOnline::updateOrCreate(
                ['user_id' => $user->id],
                ['ip_address' => $ipAddress, 'updated_at' => now()]
            );
            
        } else {
            //Log::info('No authenticated user.');
        }

        return $response;
    }
}
