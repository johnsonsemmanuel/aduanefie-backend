<?php

namespace App\Http\Middleware;

use App\Models\DeliveryMan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guards the rider portal.
 *
 * Deliberately not the framework's `auth:delivery_men`: Authenticate::redirectTo()
 * resolves `route('login')`, which requires a `{tab}` parameter, so a guest
 * hitting a rider page would throw a URL generation error instead of being
 * sent to the rider login.
 *
 * The account gates are re-checked on every request, not just at login, so a
 * rider who is suspended or un-approved mid-session loses access immediately
 * instead of keeping a working session until it expires.
 */
class RiderAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('delivery_men')->check()) {
            return redirect()->route('rider.login');
        }

        $rider = DeliveryMan::find(Auth::guard('delivery_men')->id());

        if (!$rider || $rider->application_status !== 'approved' || !$rider->status) {
            // Same reason as RiderAuthController: full logout() would touch the
            // absent remember_token column via GenericUser and throw.
            Auth::guard('delivery_men')->logoutCurrentDevice();

            return redirect()->route('rider.login');
        }

        return $next($request);
    }
}
