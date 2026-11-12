<?php

namespace App\Http\Middleware;

use App\Services\AdminMfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Refuses panel access to any admin session that has not completed a second
 * factor in this session.
 *
 * This runs *in addition* to the login-time check, not instead of it. It is the
 * backstop for every path that can create an admin session: the dedicated
 * login, the legacy four-role login, the DriveMond bridge and a remembered
 * recaller cookie. If any of them establishes a session without MFA, this
 * middleware ends that session and sends the admin back to the password step
 * rather than degrading to password-only access.
 */
class AdminMfa
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        // Guests are handled by the 'admin' middleware that runs before this.
        if (!$admin) {
            return $next($request);
        }

        if ($this->mfa->isVerifiedFor($admin)) {
            return $next($request);
        }

        Auth::guard('admin')->logout();
        $this->mfa->clear($request);

        return redirect()->route('admin.login')->withErrors([
            'email' => 'Two-factor verification is required. Please sign in again.',
        ]);
    }
}
