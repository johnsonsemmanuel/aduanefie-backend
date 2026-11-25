<?php

namespace App\Traits;

use Exception;
use App\Models\Setting;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;

trait  Processor
{
    public function response_formatter($constant, $content = null, $errors = []): array
    {
        $constant = (array)$constant;
        $constant['content'] = $content;
        $constant['errors'] = $errors;
        return $constant;
    }

    public function error_processor($validator): array
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => self::translate($error[0])];
        }
        return $errors;
    }

    public function translate($key)
    {
        try {
            App::setLocale('en');
            $lang_array = include(base_path('resources/lang/' . 'en' . '/lang.php'));
            $processed_key = ucfirst(str_replace('_', ' ', str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', $key)));
            if (!array_key_exists($key, $lang_array)) {
                $lang_array[$key] = $processed_key;
                $str = "<?php return " . var_export($lang_array, true) . ";";
                file_put_contents(base_path('resources/lang/' . 'en' . '/lang.php'), $str);
                $result = $processed_key;
            } else {
                $result = __('lang.' . $key);
            }
            return $result;
        } catch (\Exception $exception) {
            return $key;
        }
    }

    public function payment_config($key, $settings_type): object|null
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return new Setting();
        }

        return (isset($config)) ? $config : null;
    }
    public static function getDisk()
    {
        $config=\App\CentralLogics\Helpers::get_business_settings('local_storage');

        return isset($config)?($config==0?'s3':'public'):'public';
    }
    public function file_uploader(string $dir, string $format, $image = null, $old_image = null)
    {
        if ($image == null) return $old_image ?? 'def.png';

        if (isset($old_image)) Storage::disk(self::getDisk())->delete($dir . $old_image);

        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
        if (!Storage::disk(self::getDisk())->exists($dir)) {
            Storage::disk(self::getDisk())->makeDirectory($dir);
        }
        Storage::disk(self::getDisk())->put($dir . $imageName, file_get_contents($image));

        return $imageName;
    }

    public function payment_response($payment_info, $payment_flag): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if (!$payment_info) {
            return redirect()->route('payment-fail', ['token' => base64_encode('')]);
        }
        $payment_info = PaymentRequest::find($payment_info->id) ?? $payment_info;
        $token_string = 'payment_method=' . $payment_info->payment_method . '&&attribute_id=' . $payment_info->attribute_id . '&&transaction_reference=' . $payment_info->transaction_id;
        if (in_array($payment_info->payment_platform, ['web', 'app']) && $payment_info['external_redirect_link'] != null) {
            return redirect($payment_info['external_redirect_link'] . '?flag=' . $payment_flag . '&&token=' . base64_encode($token_string));
        }
        return redirect()->route('payment-' . $payment_flag, ['token' => base64_encode($token_string)]);
    }

    /**
     * Atomically finalize a payment request, verify amount/currency/reference,
     * and execute the downstream success_hook inside a row-locked transaction.
     *
     * - Duplicate or concurrent callbacks see `is_paid === 1` after acquiring
     *   the row lock and return the existing record without re-running the hook.
     * - If the downstream `success_hook` throws an exception, the transaction
     *   rolls back (`is_paid` remains `0`) so a subsequent retry can complete.
     */
    protected function finalizePaymentSuccess(
        string $lookupColumn,
        string|int $lookupValue,
        string $gateway,
        string $transactionId,
        ?float $verifiedAmount = null,
        ?string $verifiedCurrency = null
    ): ?PaymentRequest {
        return DB::transaction(function () use (
            $lookupColumn,
            $lookupValue,
            $gateway,
            $transactionId,
            $verifiedAmount,
            $verifiedCurrency
        ) {
            /** @var PaymentRequest|null $payment */
            $payment = PaymentRequest::where($lookupColumn, $lookupValue)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return null;
            }

            if ((int) $payment->is_paid === 1) {
                return $payment;
            }

            if ($verifiedAmount !== null) {
                $expected = round((float) $payment->payment_amount, 2);
                $actual = round($verifiedAmount, 2);
                if ($actual + 0.01 < $expected) {
                    throw new \RuntimeException("Payment amount mismatch: expected {$expected}, got {$actual}.");
                }
            }

            if ($verifiedCurrency !== null && !empty($payment->currency_code)) {
                if (strtoupper(trim($verifiedCurrency)) !== strtoupper(trim((string) $payment->currency_code))) {
                    throw new \RuntimeException("Payment currency mismatch: expected {$payment->currency_code}, got {$verifiedCurrency}.");
                }
            }

            if ($transactionId !== '') {
                $duplicateRef = PaymentRequest::where('transaction_id', $transactionId)
                    ->where('id', '!=', $payment->id)
                    ->where('is_paid', 1)
                    ->exists();
                if ($duplicateRef) {
                    throw new \RuntimeException("Duplicate transaction reference: {$transactionId}.");
                }
            }

            $payment->payment_method = $gateway;
            $payment->is_paid = 1;
            $payment->transaction_id = $transactionId;
            $payment->save();

            if (!empty($payment->success_hook) && function_exists($payment->success_hook)) {
                call_user_func($payment->success_hook, $payment);
            }

            return $payment->fresh();
        });
    }

    /**
     * Atomically record a failed payment attempt only if the payment has not
     * already succeeded (`is_paid === 0`). Never downgrades a paid request.
     */
    protected function finalizePaymentFailure(string $lookupColumn, string|int $lookupValue): ?PaymentRequest
    {
        return DB::transaction(function () use ($lookupColumn, $lookupValue) {
            /** @var PaymentRequest|null $payment */
            $payment = PaymentRequest::where($lookupColumn, $lookupValue)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return null;
            }

            if ((int) $payment->is_paid === 1) {
                return $payment;
            }

            if (!empty($payment->failure_hook) && function_exists($payment->failure_hook)) {
                call_user_func($payment->failure_hook, $payment);
            }

            return $payment->fresh();
        });
    }
}
