<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckClientIP
{
    /**
     * List of allowed IP addresses.
     *
     * @var array
     */
    protected $allowedIps = [
        '123.456.789.0', // Replace with actual allowed IPs
        '111.222.333.444',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Retrieve the client's IP address
        $ip = $request->ip();

        // Log the IP address and the accessed route
        Log::info('Route accessed by IP: ' . $ip . ' | Route: ' . $request->path());

        // Check if the IP is in the allowed list
        if (!in_array($ip, $this->allowedIps)) {
            // Optionally, log unauthorized access attempts
            Log::warning('Unauthorized access attempt from IP: ' . $ip);

            // Abort with a 403 Forbidden response
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
