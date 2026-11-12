<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesHuman;
use App\Models\BusinessSetting;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Dedicated store-owner and store-employee login.
 *
 * This is the canonical front door for store management
 * (admin.aduanefie.com/store-login). It is deliberately separate from
 * LoginController: that controller resolves four roles out of a settings map
 * and only understands 'admin' and 'vendor'.
 *
 * Order matters here. The account gates below (module availability,
 * subscription, approval, suspension) run *after* the password has been
 * verified. A gate must never tell an unauthenticated person whether an email
 * exists or what state its store is in.
 */
class StoreAuthController extends Controller
{
    use VerifiesHuman;

    public function showLogin()
    {
        return $this->show('vendor');
    }

    public function showEmployeeLogin()
    {
        return $this->show('vendor_employee');
    }

    public function login(Request $request)
    {
        return $this->attempt($request, 'vendor');
    }

    public function loginEmployee(Request $request)
    {
        return $this->attempt($request, 'vendor_employee');
    }

    private function show(string $role)
    {
        // A completed store session has no reason to sit on the login page.
        if (Auth::guard('vendor')->check() || Auth::guard('vendor_employee')->check()) {
            return redirect()->route('vendor.dashboard');
        }

        [$lang, $direction] = $this->localeAndDirection();

        $locale = session()?->get('vendor_local') ?? $lang;
        $siteDirection = session()?->get('vendor_site_direction') ?? $direction;
        App::setLocale($locale);

        return view('auth.login', [
            'role' => $role,
            'site_direction' => $siteDirection,
            'locale' => $locale,
            'email' => null,
            'password' => null,
            'postRoute' => $role === 'vendor_employee'
                ? route('store.employee.login.submit')
                : route('store.login.submit'),
            'mfa_required' => false,
            'mfa_mode' => null,
        ]);
    }

    private function attempt(Request $request, string $role)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$this->passesHumanCheck($request)) {
            return back()->withInput($request->only('email', 'remember'));
        }

        $guard = Auth::guard($role);
        $provider = $guard->getProvider();

        /** @var Vendor|VendorEmployee|null $user */
        $user = $provider->retrieveByCredentials(['email' => $request->email]);

        // Verify the password before looking at anything else about the
        // account. This is the gap the old shared controller had: it checked
        // store state first, which leaks the existence and state of an email
        // address to someone who does not know its password.
        if (!$user || !$provider->validateCredentials($user, ['password' => $request->password])) {
            return $this->failed($request);
        }

        $store = $role === 'vendor'
            ? $user->stores->first()
            : $user->store;

        if (!$store) {
            return $this->failed($request);
        }

        if ($store->module?->module_type === 'rental' && !addon_published_status('Rental')) {
            return $this->rejected($request, translate('messages.rental_module_is_not_available'));
        }

        if ($role === 'vendor') {
            if ($store->store_business_model === 'none') {
                $key = ['subscription_free_trial_days', 'subscription_free_trial_type', 'subscription_free_trial_status'];
                $free_trial_settings = BusinessSetting::whereIn('key', $key)->pluck('value', 'key');

                return view('vendor-views.auth.register-subscription-payment', [
                    'package_id' => $store->package_id,
                    'store_id' => $store->id,
                    'free_trial_settings' => $free_trial_settings,
                    'payment_methods' => \App\CentralLogics\Helpers::getDefaultPaymentMethods(),
                ]);
            }

            // Mirrors the legacy store gate: the vendor account itself may be
            // active while the store is still pending review.
            if ($store->status == 0 && $user->status == 0) {
                return $this->rejected($request, translate('messages.Admin_did_not_approve_your_registration_yet.'));
            }
        } else {
            if (in_array($store->store_business_model, ['none', 'unsubscribed'], true) || $store->status == 0) {
                return $this->rejected($request, translate('messages.store_is_inactive'));
            }
        }

        $remember = $request->boolean('remember');

        if ($remember) {
            $guard->login($user, true);
        } else {
            // Do not leave a persistent token behind from an earlier session.
            $user->forceFill(['remember_token' => null])->save();
            $guard->login($user, false);
        }

        // Session fixation: the pre-login session id must not survive the
        // guest-to-authenticated transition.
        $request->session()->regenerate();

        if ($role === 'vendor_employee') {
            $user->is_logged_in = 1;
            $user->save();
        }

        $isRental = $store->module?->module_type === 'rental' && addon_published_status('Rental');

        return redirect()->route($isRental ? 'vendor.providerDashboard' : 'vendor.dashboard');
    }

    private function failed(Request $request)
    {
        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => 'Credentials does not match.']);
    }

    private function rejected(Request $request, string $message)
    {
        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => $message]);
    }
}
