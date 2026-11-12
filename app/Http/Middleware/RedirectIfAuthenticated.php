<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {

        switch ($guard) {
            case 'admin':
                if (Auth::guard($guard)->check()) {
                    // An admin session that has not passed the second factor must
                    // not be treated as a finished login. End it here; the login
                    // form is the only place that can restart the flow.
                    if (app(\App\Services\AdminMfaService::class)->isVerifiedFor(Auth::guard($guard)->user())) {
                        return redirect()->route('admin.dashboard');
                    }
                    Auth::guard($guard)->logout();
                }
                break;
            case 'vendor':
                if (Auth::guard($guard)->check()) {
                    return redirect()->route('vendor.dashboard');
                }
                break;
            default:
                if (Auth::guard($guard)->check()) {
                    return response()->json([],404);
                }
                break;
        }

        return $next($request);
    }
}
