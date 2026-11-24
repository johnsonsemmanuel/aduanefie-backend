<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Concerns\VerifiesHuman;
use App\Models\Admin;
use App\Services\AdminMfaService;
use App\Traits\SmsGateway;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

/**
 * Login for the remote-marketer portal on partners.aduanefie.com.
 *
 * Marketers are admins with role 5. This portal deliberately reuses the admin
 * guard and the admin second-factor service rather than introducing a second
 * credential path for the same accounts. A separate guard over the same table
 * would be a weaker door into accounts that can request money, which is exactly
 * the silent downgrade the MFA work exists to prevent.
 *
 * The role and password are checked *before* any session is created, and the
 * password alone never produces a usable session: AdminMfaService::begin()
 * destroys it and leaves only a short-lived pending marker.
 */
class MarketerAuthController extends Controller
{
    use VerifiesHuman;

    public function __construct(private AdminMfaService $mfa)
    {
    }

    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();

            if ($admin->isMarketer() && $this->mfa->isVerifiedFor($admin)) {
                return redirect()->route('marketer.dashboard');
            }

            // Half-authenticated or not a marketer: do not carry the session
            // into this portal.
            Auth::guard('admin')->logout();
        }

        [$lang, $direction] = $this->localeAndDirection();

        $locale = session()?->get('local') ?? $lang;
        $siteDirection = session()?->get('site_direction') ?? $direction;
        App::setLocale($locale);

        $captcha = new CaptchaBuilder;
        $captcha->build();
        Session::put('six_captcha', $captcha->getPhrase());

        // Same login component the store and admin portals use. Only the
        // destination route, label and human check are marketer-specific; MFA
        // remains on the dedicated setup/challenge routes after credentials
        // are accepted.
        return view('auth.login', [
            'role' => 'marketer',
            'site_direction' => $siteDirection,
            'locale' => $locale,
            'email' => null,
            'password' => null,
            'postRoute' => route('marketer.login.submit'),
            'forgotRoute' => route('marketer.forgot'),
            'mfa_required' => false,
            'mfa_mode' => null,
            'captcha' => $captcha,
            'recaptcha' => Helpers::get_business_settings('recaptcha'),
            'recaptcha_action' => 'marketer_login',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'password' => 'required|min:6',
        ]);

        if (!$this->passesHumanCheck($request)) {
            return back()->withInput($request->only('email'));
        }

        $admin = Admin::where('email', $request->email)
            ->where('role_id', config('marketer.role_id', 5))
            ->first();

        if (!$admin || !$admin->password || !Hash::check((string) $request->password, $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => translate('messages.Incorrect_credential,_please_try_again')]);
        }

        // begin() destroys the password-only session and leaves only a short
        // lived pending marker. There is deliberately no branch that creates a
        // usable session from a password alone.
        $mode = $this->mfa->begin($request, $admin, false);

        return redirect()->route(
            $mode === AdminMfaService::MODE_SETUP
                ? 'marketer.mfa.setup'
                : 'marketer.mfa.challenge'
        );
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketer.login');
    }

    public function showForgotPassword()
    {
        return view('marketer.forgot');
    }

    public function sendLoginLink(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:120',
        ]);

        $raw = trim((string) $request->input('identifier'));
        $roleId = (int) config('marketer.role_id', 5);
        $digits = preg_replace('/\D+/', '', $raw);

        $admin = Admin::where('role_id', $roleId)
            ->where(function ($q) use ($raw, $digits) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($raw)]);
                if (strlen($digits) >= 7) {
                    $tail = substr($digits, -9);
                    $q->orWhere('phone', 'like', "%{$tail}");
                }
            })
            ->first();

        if ($admin) {
            self::sendReengagementNotice($admin, 1);
        }

        return back()->with(
            'status',
            'If an active Regional Marketer account matches that email or phone, we have sent a one-click sign-in link via SMS and email.'
        );
    }

    public function magicLogin(Request $request, int $id)
    {
        $admin = Admin::where('id', $id)
            ->where('role_id', (int) config('marketer.role_id', 5))
            ->first();

        if (! $admin) {
            return redirect()->route('marketer.login')->withErrors([
                'email' => 'Marketer account not found.',
            ]);
        }

        // A signed link proves the mailbox/phone, not the person. It lands in
        // the same MFA setup/challenge as a password login and never creates
        // a session by itself.
        $mode = $this->mfa->begin($request, $admin, false);

        return redirect()->route(
            $mode === AdminMfaService::MODE_SETUP
                ? 'marketer.mfa.setup'
                : 'marketer.mfa.challenge'
        );
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Auth::guard('admin')->user();
        if (! $admin || ! $admin->isMarketer()) {
            abort(403);
        }

        $admin->forceFill([
            'password' => Hash::make((string) $request->input('new_password')),
        ])->save();

        return back()->with('status', 'Your marketer portal password has been updated.');
    }

    public static function buildSignedLoginUrl(Admin $admin, int $hours = 168): string
    {
        $root = 'https://admin.aduanefie.com';
        URL::forceRootUrl($root);
        try {
            return URL::temporarySignedRoute(
                'marketer.magic-login',
                now()->addHours($hours),
                ['id' => $admin->id]
            );
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public static function sendReengagementNotice(Admin $admin, int $hours = 168): array
    {
        if (empty($admin->marketer_code)) {
            $regionSlug = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($admin->marketer_region ?: 'GH')), 0, 2) ?: 'GH');
            $admin->forceFill([
                'marketer_code' => 'MKT-' . $regionSlug . '-' . str_pad((string) $admin->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        $loginLink = self::buildSignedLoginUrl($admin, $hours);
        $code = $admin->marketer_code;
        $firstName = trim((string) ($admin->f_name ?: 'Partner'));

        $smsSent = false;
        if (! empty($admin->phone)) {
            try {
                $smsText = "Hi {$firstName}, Aduanefie Partners Portal is upgraded! Sign in directly: {$loginLink} (Email: {$admin->email}, Code: {$code}). Earn 25 GHS/farmer + 20% order commissions.";
                $smsSent = SmsGateway::send($admin->phone, $smsText) === 'success';
            } catch (\Throwable $e) {
                Log::warning('Marketer re-engagement SMS failed', ['id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }

        $emailSent = false;
        if (! empty($admin->email)) {
            try {
                $body = "Hello {$firstName},\n\n"
                    . "Welcome back to the Aduanefie Regional Marketers Portal!\n\n"
                    . "Use your one-click sign-in link below to access your dashboard, view Regional Tours, verify Backyard Farms, and track your commissions:\n"
                    . "{$loginLink}\n\n"
                    . "Account Email: {$admin->email}\n"
                    . "Referral Code: {$code}\n"
                    . "Portal URL: https://admin.aduanefie.com/marketer-login\n\n"
                    . "Once signed in, you can set a new password at the bottom of your dashboard.\n\n"
                    . "— Aduanefie Partner Operations";

                Mail::raw($body, function ($message) use ($admin) {
                    $message->to($admin->email, trim($admin->f_name . ' ' . $admin->l_name))
                        ->subject('Sign in to your Aduanefie Regional Marketer Portal');
                });
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('Marketer re-engagement Email failed', ['id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }

        return ['sms' => $smsSent, 'email' => $emailSent];
    }
}
