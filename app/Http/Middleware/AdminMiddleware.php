<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(Auth::guard('admin')->user() && Auth::guard('admin')->user()->is_logged_in == 0){
            auth()->guard('admin')->logout();
        }
        if (Auth::guard('admin')->check()) {
            // Mandatory second factor, enforced from the one middleware every
            // admin route already uses. There are two admin route files
            // (routes/admin.php and routes/admin/routes.php); putting the check
            // here rather than in each group means a third file cannot be
            // added later without the gate. AdminMfa holds the rules.
            return app(AdminMfa::class)->handle($request, $next);
        }
        return redirect()->route('home');
        // return redirect()->route('login');
        // return redirect()->route('admin.auth.login');
    }
}
