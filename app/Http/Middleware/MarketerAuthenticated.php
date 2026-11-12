<?php

namespace App\Http\Middleware;

use App\Services\AdminMfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guards the marketer portal.
 *
 * Checks are re-run on every request, not just at login, so an account whose
 * role is changed away from marketer, whose MFA enrolment is reset, or whose
 * session was never verified loses access immediately. On any failure the
 * session is destroyed rather than merely redirected, so no
 * authenticated-but-unverified state survives.
 */
class MarketerAuthenticated
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin
            || !$admin->isMarketer()
            || (int) $admin->is_logged_in === 0
            || !$this->mfa->isVerifiedFor($admin)) {
            Auth::guard('admin')->logout();

            return redirect()->route('marketer.login');
        }

        return $next($request);
    }
}
