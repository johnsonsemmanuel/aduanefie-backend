<?php

namespace App\Http\Controllers;

    use App\Models\Admin;
    use App\Models\ExternalConfiguration;
    use App\Models\Vendor;
    use App\Models\DataSetting;
    use Illuminate\Http\Request;
    use App\Services\AdminMfaService;
    use App\CentralLogics\Helpers;
    use App\Models\VendorEmployee;
    use Illuminate\Support\Carbon;
    use App\Models\BusinessSetting;
    use App\CentralLogics\SMS_module;
    use App\Models\PhoneVerification;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;
    use App\Models\SubscriptionPackage;
    use Illuminate\Support\Facades\App;
    use App\Mail\AdminPasswordResetMail;
    use Brian2694\Toastr\Facades\Toastr;
    use Illuminate\Support\Facades\Mail;
    use App\Mail\PasswordResetRequestMail;
    use Illuminate\Support\Facades\Cookie;
    use Modules\Gateways\Traits\SmsGateway;
    use Illuminate\Validation\Rules\Password;
    use App\Models\Cart;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:admin,vendor', ['except' => 'logout']);
    }

    public function login($login_url)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        if ($language) {
            foreach (json_decode($language->value, true) as $key => $data) {
                if ($data['default']) {
                    $lang = $data['code'];
                    $direction = $data['direction'];
                }
            }
        }
        $data = array_column(DataSetting::whereIn('key', ['store_employee_login_url', 'store_login_url', 'admin_employee_login_url', 'admin_login_url'
        ])->get(['key', 'value'])->toArray(), 'value', 'key');

        $loginTypes = [
            'admin' => 'admin_login_url',
            'admin_employee' => 'admin_employee_login_url',
            'vendor' => 'store_login_url',
            'vendor_employee' => 'store_employee_login_url'
        ];
        $siteDirections = [
            'admin' => session()?->get('site_direction') ?? $direction ?? 'ltr',
            'admin_employee' => session()?->get('site_direction') ?? $direction ?? 'ltr',
            'vendor' => session()?->get('vendor_site_direction') ?? $direction ?? 'ltr',
            'vendor_employee' => session()?->get('vendor_site_direction') ?? $direction ?? 'ltr'
        ];
        $locals = [
            'admin' => session()?->get('local') ?? $lang ?? 'en',
            'admin_employee' => session()?->get('local') ?? $lang ?? 'en',
            'vendor' => session()?->get('vendor_local') ?? $lang ?? 'en',
            'vendor_employee' => session()?->get('vendor_local') ?? $lang ?? 'en'
        ];
        $role = null;

        $user_type = array_search($login_url, $data);
        abort_if(!$user_type, 404);
        $role = array_search($user_type, $loginTypes, true);

        abort_if($role == null, 404);
        $site_direction = $siteDirections[$role];
        $locale = $locals[$role];
        App::setLocale($locale);

        $email = null;
        $password = null;
        if (Cookie::has('p_token') && Cookie::has('e_token') && Cookie::has('role') && Cookie::get('role') == $role) {
            $email = Crypt::decryptString(Cookie::get('e_token'));
            $password = Crypt::decryptString(Cookie::get('p_token'));
        }

        return view('auth.login', compact('email', 'password', 'role', 'site_direction', 'locale'));
    }

    public function login_attemp($role, $email, $password, $remember = false)
    {
        $auth = ($role == 'admin_employee' ? 'admin' : $role);
        if (auth($auth)->attempt(['email' => $email, 'password' => $password], $remember)) {

            // Both admin login tabs land on the same guard, so this branch is
            // the only place the legacy /login/<tab> entry can be gated. The
            // password-only session is torn down by begin(); 'remember' is
            // carried in the pending marker and applied after the challenge.
            if ($auth == 'admin') {
                $mode = app(AdminMfaService::class)
                    ->begin(request(), auth('admin')->user(), (bool) $remember);

                return $mode === AdminMfaService::MODE_SETUP
                    ? 'admin_mfa_setup'
                    : 'admin_mfa';
            }

            if ($remember) {
                Cookie::queue('role', $role, 120);
                Cookie::queue('e_token', Crypt::encryptString($email), 120);
                Cookie::queue('p_token', Crypt::encryptString($password), 120);
            } else {
                $user = auth($auth)?->user();
                $user?->update([
                    'remember_token' => null
                ]);
                Cookie::forget('role');
                Cookie::forget('e_token');
                Cookie::forget('p_token');
            }
            if ($auth == 'admin') {
                return 'admin';
            } else {
                return 'vendor';
            }
        }
        return false;
    }


    public function externalLoginFromDrivemond(Request $request)
    {
        $drivemondToken = ExternalConfiguration::where(['key' => 'drivemond_token'])->first()->value ?? null;
        $systemSelfToken = ExternalConfiguration::where(['key' => 'system_self_token'])->first()->value ?? null;
        $drivemondBaseUrl = ExternalConfiguration::where(['key' => 'drivemond_base_url'])->first()->value ?? null;
        if ($drivemondBaseUrl != null && $drivemondToken != null && $systemSelfToken != null) {
            $user = Admin::where('role_id', 1)->first();
            if (isset($user)) {
                // DriveMond SSO would create a master-admin session with no
                // password, and therefore with no second factor. Under the
                // mandatory-MFA policy that must not grant panel access. The
                // bridge now stops at the password step; re-enabling it needs a
                // second-factor handshake in the DriveMond flow itself.
                return redirect()->route('admin.login')->withErrors([
                    'email' => 'Single sign-on cannot complete two-factor verification. Sign in with your password.',
                ]);
            }
        }
        return redirect()->route('admin.login');
    }


    /**
     * Check a password without starting a session.
     *
     * Uses the same guard/provider the real login below uses, so a password
     * accepted here is exactly the password `login_attemp()` will accept.
     */
    private function credentialsAreValid(string $guard, $user, string $password): bool
    {
        return auth($guard)->getProvider()->validateCredentials($user, ['password' => $password]);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'role' => 'required'
        ]);

        if ($request->role == 'admin_employee') {
            $data = Admin::where('email', $request->email)->where('role_id', 1)->exists();
            if ($data) {
                return redirect()->back()->withInput($request->only('email', 'remember'))
                    ->withErrors(['Credentials does not match.']);
            }
        }
        elseif ($request->role == 'admin') {
            $data = Admin::where('email', $request->email)->where('role_id', 1)->exists();
            if (!$data) {
                return redirect()->back()->withInput($request->only('email', 'remember'))
                    ->withErrors(['Credentials does not match.']);
            }
        }
        elseif ($request->role == 'vendor') {
            $vendor = Vendor::where('email', $request->email)->first();

            // Verify the password before any account-state gate. The gates
            // below must never tell an unauthenticated caller whether an
            // email exists or what state its store is in.
            if (!$vendor || !$this->credentialsAreValid('vendor', $vendor, $request->password)) {
                return redirect()->back()->withInput($request->only('email', 'remember'))
                    ->withErrors(['Credentials does not match.']);
            }

            if ($vendor) {
                if($vendor?->stores[0]?->module?->module_type == 'rental'){
                    if(!addon_published_status('Rental')){
                        return redirect()->back()->withInput($request->only('email', 'remember'))
                        ->withErrors([translate('messages.rental_module_is_not_available')]);
                    }
                }
                if ($vendor?->stores[0]?->store_business_model == 'none') {
                    $key = ['subscription_free_trial_days', 'subscription_free_trial_type', 'subscription_free_trial_status'];
                    $free_trial_settings = BusinessSetting::whereIn('key', $key)->pluck('value', 'key');

                    return view('vendor-views.auth.register-subscription-payment', [
                        'package_id' => $vendor?->stores[0]?->package_id,
                        'store_id' => $vendor?->stores[0]?->id,
                        'free_trial_settings' => $free_trial_settings,
                        'payment_methods' => Helpers::getDefaultPaymentMethods(),
                    ]);
                }

                if ($vendor?->stores[0]?->status == 0 && $vendor?->status == 0) {
                    return redirect()->back()->withInput($request->only('email', 'remember'))
                        ->withErrors([translate('messages.Admin_did_not_approve_your_registration_yet.')]);
                }
            }
        } elseif ($request->role == 'vendor_employee') {
            $employee = VendorEmployee::where('email', $request->email)->first();

            // Same ordering rule as the owner branch above.
            if (!$employee || !$this->credentialsAreValid('vendor_employee', $employee, $request->password)) {
                return redirect()->back()->withInput($request->only('email', 'remember'))
                    ->withErrors(['Credentials does not match.']);
            }

                if($employee?->store?->module?->module_type == 'rental'){
                    if(!addon_published_status('Rental')){
                        return redirect()->back()->withInput($request->only('email', 'remember'))
                        ->withErrors([translate('messages.rental_module_is_not_available')]);
                    }
                }
                if ($employee && (in_array($employee?->store?->store_business_model, ['none', 'unsubscribed']) || $employee?->store?->status == 0)) {
                    return redirect()->back()->withInput($request->only('email', 'remember'))
                        ->withErrors([translate('messages.store_is_inactive')]);
                }
        }

        $data = $this->login_attemp($request->role, $request->email, $request->password, $request->remember);

        if ($data === 'admin_mfa' || $data === 'admin_mfa_setup') {
            return redirect()->route(
                $data === 'admin_mfa_setup' ? 'admin.mfa.setup' : 'admin.mfa.challenge'
            );
        }

        // Migrate guest cart items to authenticated user
        if ($request->role !== 'admin' && $request->has('guest_id')) {
            $guestId = $request->guest_id;
            $authUserId = auth($request->role)->id();
            if ($guestId && $authUserId) {
                $guestCarts = Cart::where('guest_id', $guestId)->where('is_guest', 1)->get();
                foreach ($guestCarts as $guestCart) {
                    $existing = Cart::where('item_id', $guestCart->item_id)
                        ->where('item_type', $guestCart->item_type)
                        ->where('variation', $guestCart->variation)
                        ->where('user_id', $authUserId)
                        ->where('is_guest', 0)
                        ->where('module_id', $guestCart->module_id)
                        ->first();

                    if ($existing) {
                        $existing->quantity = $existing->quantity + $guestCart->quantity;
                        $existing->save();
                        $guestCart->delete();
                    } else {
                        $guestCart->user_id = $authUserId;
                        $guestCart->is_guest = 0;
                        $guestCart->save();
                    }
                }
            }
        }

        // There is deliberately no '$data == admin' branch here: both admin
        // tabs return admin_mfa* above. Re-entering the panel without the
        // second factor is not a code path that exists.
        if ($data == 'vendor') {
            if ($request->role === 'vendor_employee') {
                $employee = VendorEmployee::where('email', $request->email)->first();
                $employee->is_logged_in = 1;
                $employee->save();
            }
            if(Helpers::get_store_data()?->module_type == 'rental' && addon_published_status('Rental')){
                return redirect()->route('vendor.providerDashboard');
            }
            return redirect()->route('vendor.dashboard');
        }

        return redirect()->back()->withInput($request->only('email', 'remember'))
            ->withErrors(['Credentials does not match.']);
    }

    public function reset_password_request(Request $request)
    {
        $admin = Admin::where('role_id', 1)->first();

        if (isset($admin)) {
            $token = Helpers::generate_reset_password_code();
            DB::table('password_resets')->insert([
                'email' => $admin['email'],
                'token' => $token,
                'created_by' => 'admin',
                'created_at' => now(),
            ]);
            $url = url('/') . '/password-reset?token=' . $token;
            try {
                if (config('mail.status') && $admin['email'] && Helpers::get_mail_status('forget_password_mail_status_admin') == '1' && Helpers::getNotificationStatusData('admin', 'forget_password', 'mail_status')) {
                    Mail::to($admin['email'])->send(new AdminPasswordResetMail($url, $admin['f_name']));
                    session()->put('log_email_succ', 1);
                } else {
                    Toastr::error(translate('messages.Failed_to_send_mail'));
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
                Toastr::error(translate('messages.Failed_to_send_mail'));
            }
            return back();
        }
        Toastr::error(translate('messages.credential_doesnt_match'));
        return back();
    }

    public function vendor_reset_password_request(Request $request)
    {
        $request->validate([
            'email' => 'required'
        ]);
        $vendor = Vendor::where('email', $request['email'])->first();

        if (isset($vendor)) {
            $token = Helpers::generate_reset_password_code();
            DB::table('password_resets')->insert([
                'email' => $vendor['email'],
                'token' => $token,
                'created_by' => 'vendor',
                'created_at' => now(),
            ]);
            $url = url('/') . '/password-reset?token=' . $token;

            try {
                if (config('mail.status') && $vendor['email']) {
                    Mail::to($vendor['email'])->send(new PasswordResetRequestMail($url, $vendor['f_name']));
                    session()->put('log_email_succ', 1);
                } else {
                    Toastr::error(translate('messages.Failed_to_send_mail'));
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
                Toastr::error(translate('messages.Failed_to_send_mail'));
            }
            return back();
        }
        Toastr::error(translate('messages.Email_does_not_exists'));
        return back();
    }

    public function reset_password(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        if ($language) {
            foreach (json_decode($language->value, true) as $key => $data) {
                if ($data['default']) {
                    $lang = $data['code'];
                    $direction = $data['direction'];
                }
            }
        }
        $data = DB::table('password_resets')->where(['token' => $request['token']])->first();
        if (!$data || Carbon::parse($data->created_at)->diffInMinutes(Carbon::now()) >= 60) {
            Toastr::error(translate('messages.link_expired'));
            return redirect()->route('home');
        }
        $token = $request['token'];
        if ($data->created_by == 'admin') {
            $admin = Admin::where('email', $data->email)->where('role_id', 1)->first();
            $otp = rand(10000, 99999);
            DB::table('phone_verifications')->updateOrInsert(['phone' => $admin['phone']],
                [
                    'token' => $otp,
                    'otp_hit_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            //for payment and sms gateway addon

            $response = null;
            if (Helpers::getNotificationStatusData('admin', 'forget_password', 'sms_status')) {
                $published_status = addon_published_status('Gateways');
                if ($published_status == 1) {
                    $response = SmsGateway::send($admin['phone'], $otp);
                } else {
                    $response = SMS_module::send($admin['phone'], $otp);
                }
            }

            $site_direction = session()?->get('site_direction') ?? $direction ?? 'ltr';
            $locale = session()?->get('local') ?? $lang ?? 'en';
            App::setLocale($locale);
            if ($response == 'success') {
                return view('auth.verify-otp', compact('token', 'admin', 'site_direction', 'locale'));
            }
            return view('auth.reset-password', compact('token', 'admin', 'site_direction', 'locale'));
        } else {
            $site_direction = session()?->get('vendor_site_direction') ?? $direction ?? 'ltr';
            $locale = session()?->get('vendor_local') ?? $lang ?? 'en';
            App::setLocale($locale);
            return view('auth.reset-password', compact('token', 'site_direction', 'locale'));
        }


    }

    public function verify_token(Request $request)
    {
        $request->validate([
            'reset_token' => 'required',
            'opt-value' => 'required',
        ]);
        $language = BusinessSetting::where('key', 'system_language')->first();
        if ($language) {
            foreach (json_decode($language->value, true) as $key => $data) {
                if ($data['default']) {
                    $lang = $data['code'];
                    $direction = $data['direction'];
                }
            }
        }
        $token = $request['reset_token'];
        $admin = Admin::where('phone', $request['phone'])->where('role_id', 1)->first();

        $data = PhoneVerification::where([
            'phone' => $request['phone'],
            'token' => $request['opt-value'],
        ])->first();

        if (isset($data)) {
            $data?->delete();
            $site_direction = session()?->get('site_direction') ?? $direction ?? 'ltr';
            $locale = session()?->get('local') ?? $lang ?? 'en';
            App::setLocale($locale);
            return view('auth.reset-password', compact('token', 'admin', 'site_direction', 'locale'));
        }

        Toastr::error(translate('messages.otp_doesnt_match'));
        return back();
    }

    public function reset_password_submit(Request $request)
    {
        $request->validate([
            'reset_token' => 'required',
            'password' => ['required', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
            'confirm_password' => 'required|same:password',
        ]);
        $data = DB::table('password_resets')->where(['token' => $request['reset_token']])->first();
        if (isset($data)) {
            if ($request['password'] == $request['confirm_password']) {
                if ($data->created_by == 'admin') {
                    DB::table('admins')->where(['email' => $data->email])->update([
                        'password' => bcrypt($request['confirm_password'])
                    ]);
                    $user_link = Helpers::get_login_url('admin_login_url');
                } else {
                    DB::table('vendors')->where(['email' => $data->email])->update([
                        'password' => bcrypt($request['confirm_password'])
                    ]);
                    $user_link = Helpers::get_login_url('store_login_url');
                }
                DB::table('password_resets')->where(['token' => $request['reset_token']])->delete();
                Toastr::success(translate('messages.password_changed_successfully'));
                return redirect()->route('login', [$user_link]);
            }
        }
        Toastr::error(translate('messages.something_went_wrong'));
        return back();

    }

    public function logout()
    {
        if (auth('vendor')?->check()) {
            $user_link = Helpers::get_login_url('store_login_url');
            auth()->guard('vendor')->logout();
            session()->forget('subscription_free_trial_close_btn');
            session()->forget('subscription_renew_close_btn');
            session()->forget('subscription_cancel_close_btn');

        } elseif (auth('vendor_employee')?->check()) {
            $user_link = Helpers::get_login_url('store_employee_login_url');
            auth()->guard('vendor_employee')->logout();
            session()->forget('subscription_free_trial_close_btn');
            session()->forget('subscription_renew_close_btn');
            session()->forget('subscription_cancel_close_btn');
        } else {
            if (auth()?->guard('admin')?->user()?->role_id == 1) {
                    $user_link = Helpers::get_login_url('admin_login_url');
                } else {
                $user_link = Helpers::get_login_url('admin_employee_login_url');
            }
            auth()?->guard('admin')?->logout();
            session()->forget([
                AdminMfaService::SESSION_PENDING,
                AdminMfaService::SESSION_VERIFIED,
            ]);
        }
        return redirect()->route('login', [$user_link]);
    }

    public function otp_resent(Request $request)
    {
        $data = DB::table('password_resets')->where(['token' => $request['token']])->first();
        if (!$data || Carbon::parse($data->created_at)->diffInMinutes(Carbon::now()) >= 60) {
            return response()->json(['errors' => 'link_expired']);
        }

        if (Helpers::getNotificationStatusData('admin', 'forget_password', 'sms_status') != 1) {
            return response()->json(['otp_fail' => 'otp_fail']);
        }

        if ($data->created_by == 'admin') {
            $admin = Admin::where('email', $data->email)->where('role_id', 1)->first();
            $otp = rand(10000, 99999);
            DB::table('phone_verifications')->updateOrInsert(['phone' => $admin['phone']],
                [
                    'token' => $otp,
                    'otp_hit_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            //for payment and sms gateway addon


            $published_status = addon_published_status('Gateways');

            if ($published_status == 1) {
                $response = SmsGateway::send($admin['phone'], $otp);
            } else {
                $response = SMS_module::send($admin['phone'], $otp);
            }


            if ($response != 'success') {
                return response()->json(['otp_fail' => 'otp_fail']);
            }
            return response()->json(['success' => 'otp_send']);

        }
    }
}
