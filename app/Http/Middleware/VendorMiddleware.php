<?php

namespace App\Http\Middleware;

use App\Services\AdminMfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorMiddleware
{
    public function __construct(private AdminMfaService $mfa)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('vendor')->check()) {
            $vendor = auth('vendor')->user();

            if (!$vendor->status) {
                auth()->guard('vendor')->logout();
                return redirect()->route('store.login');
            }

            // If the vendor has enrolled in MFA, require a verified session.
            if ($vendor->hasConfirmedTwoFactor() && !$this->mfa->isVerifiedFor($vendor, 'vendor')) {
                auth()->guard('vendor')->logout();
                $this->mfa->clear($request, 'vendor');

                return redirect()->route('store.login')->withErrors([
                    'email' => 'Two-factor verification is required. Please sign in again.',
                ]);
            }

            return $next($request);
        }
        else if (Auth::guard('vendor_employee')->check()) {
            if(Auth::guard('vendor_employee')->user()->is_logged_in == 0)
            {
                auth()->guard('vendor_employee')->logout();
                return redirect()->route('store.login');
            }
            if(!auth('vendor_employee')->user()->store->status)
            {
                auth()->guard('vendor_employee')->logout();
                return redirect()->route('store.login');
            }
            return $next($request);
        }
        return redirect()->route('store.login');
    }
}
