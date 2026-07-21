<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActivationCheckMiddleware
{
    /**
     * Licensing removed — always pass through.
     */
    public function handle(Request $request, Closure $next, $area = null): mixed
    {
        return $next($request);
    }
}
