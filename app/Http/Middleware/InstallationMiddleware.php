<?php

namespace App\Http\Middleware;

use Closure;

class InstallationMiddleware
{
    /**
     * Licensing removed — always pass through.
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
