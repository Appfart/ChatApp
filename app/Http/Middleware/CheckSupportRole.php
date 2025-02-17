<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckSupportRole
{
    public function handle($request, Closure $next)
    {
        if (in_array(Auth::user()->role, ['support', 'admin', 'superadmin'])) {
            return $next($request);
        }

        abort(403);
    }
}
