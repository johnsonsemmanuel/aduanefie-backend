<?php

namespace App\Http\Controllers\Concerns;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * The captcha and locale handling shared by the dedicated login controllers.
 *
 * This exists so the two-mode captcha check (Google reCAPTCHA when enabled,
 * image captcha otherwise) is written once. A second copy of security code is
 * how the copies drift apart, so admin and rider login both use this trait.
 */
trait VerifiesHuman
{
    /**
     * Returns false after flashing its own error, so callers can simply
     * redirect back without inspecting the reason.
     */
    protected function passesHumanCheck(Request $request): bool
    {
        $recaptcha = Helpers::get_business_settings('recaptcha');

        if (isset($recaptcha) && $recaptcha['status'] == 1 && !$request->set_default_captcha) {
            $secretKey = Helpers::get_business_settings('recaptcha')['secret_key'];
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$response->successful()) {
                Toastr::error(translate('ReCaptcha Failed'));
                return false;
            }

            return true;
        }

        if (strtolower((string) session('six_captcha')) !== strtolower((string) $request->input('custome_recaptcha'))) {
            Toastr::error(translate('messages.ReCAPTCHA Failed'));
            return false;
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string} [locale, direction]
     */
    protected function localeAndDirection(): array
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $lang = 'en';
        $direction = 'ltr';

        if ($language) {
            foreach (json_decode($language->value, true) ?? [] as $item) {
                if (!empty($item['default'])) {
                    $lang = $item['code'];
                    $direction = $item['direction'];
                    break;
                }
            }
        }

        return [$lang, $direction];
    }
}
