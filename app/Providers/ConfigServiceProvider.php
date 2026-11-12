<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Module;
use App\Models\Setting;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
Carbon::setWeekStartsAt(Carbon::MONDAY);
Carbon::setWeekEndsAt(Carbon::SUNDAY);
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $mode = env('APP_MODE');
        try {
            $gateway = ['paytm', 'razor_pay', 'flutterwave', 'paypal', 'ssl_commerz', 'paystack'];
            $bsKeys = [
                'mail_config',
                'order_delivery_verification',
                'default_pagination',
                'digit_after_decimal_point',
                'dm_maximum_orders',
                'order_confirmation_model',
                'timezone',
                'timeformat',
                'canceled_by_store',
                'canceled_by_deliveryman',
                'toggle_veg_non_veg',
                's3_credential',
                'local_storage',
            ];

            $bootSettings = Cache::remember('boot_config_provider_v1', now()->addMinutes(3), function () use ($gateway, $bsKeys) {
                return [
                    'bs' => BusinessSetting::whereIn('key', $bsKeys)->pluck('value', 'key')->toArray(),
                    'gw' => Setting::whereIn('key_name', $gateway)->pluck('live_values', 'key_name')->toArray(),
                ];
            });

            $bs = $bootSettings['bs'] ?? [];
            $data = $bootSettings['gw'] ?? [];

            $emailServices = isset($bs['mail_config']) ? json_decode($bs['mail_config'], true) : null;
            if ($emailServices) {
                $config = array(
                    'status' => (Boolean)(isset($emailServices['status']) ? $emailServices['status'] : 1),
                    'driver' => $emailServices['driver'],
                    'host' => $emailServices['host'],
                    'port' => $emailServices['port'],
                    'username' => $emailServices['username'],
                    'password' => $emailServices['password'],
                    'encryption' => $emailServices['encryption'],
                    'from' => array('address' => $emailServices['email_id'], 'name' => $emailServices['name']),
                    'sendmail' => '/usr/sbin/sendmail -bs',
                    'pretend' => false,
                );
                Config::set('mail', $config);
            }
            if (isset($data['paystack'])) {
                $config = array(
                    'publicKey' => env('PAYSTACK_PUBLIC_KEY',data_get($data,'paystack.public_key',null)),
                    'secretKey' => env('PAYSTACK_SECRET_KEY', data_get($data,'paystack.secret_key',null)),
                    'paymentUrl' => env('PAYSTACK_PAYMENT_URL',data_get($data,'paystack.callback_url',null)),
                    'merchantEmail' => env('MERCHANT_EMAIL', data_get($data,'paystack.merchant_email',null)),
                );
                Config::set('paystack', $config);
            }


            if (data_get($data,'ssl_commerz',null)) {
                if ( data_get($data,'ssl_commerz.mode',null) == 'live') {
                    $url = "https://securepay.sslcommerz.com";
                    $host = false;
                } else {
                    $url = "https://sandbox.sslcommerz.com";
                    $host = true;
                }
                $config = array(
                    'projectPath' => env('PROJECT_PATH'),
                    'apiDomain' => env("API_DOMAIN_URL", $url),
                    'apiCredentials' => [
                        'store_id' => data_get($data,'ssl_commerz.store_id',null),
                        'store_password' => data_get($data,'ssl_commerz.store_password',null),
                    ],
                    'apiUrl' => [
                        'make_payment' => "/gwprocess/v4/api.php",
                        'transaction_status' => "/validator/api/merchantTransIDvalidationAPI.php",
                        'order_validate' => "/validator/api/validationserverAPI.php",
                        'refund_payment' => "/validator/api/merchantTransIDvalidationAPI.php",
                        'refund_status' => "/validator/api/merchantTransIDvalidationAPI.php",
                    ],
                    'connect_from_localhost' => env("IS_LOCALHOST", $host), // For Sandbox, use "true", For Live, use "false"
                    'success_url' => '/success',
                    'failed_url' => '/fail',
                    'cancel_url' => '/cancel',
                    'ipn_url' => '/ipn',
                );
                Config::set('sslcommerz', $config);
            }

            if (data_get($data,'paypal',null)) {
                if (data_get($data,'paypal.mode',null) == 'live') {
                    $paypal_mode = "live";
                } else {
                    $paypal_mode = "sandbox";
                }
                $config = array(
                    'client_id' => data_get($data,'paypal.client_id',null), // values : (local | production)
                    'secret' => data_get($data,'paypal.client_secret',null),
                    'settings' => array(
                        'mode' => env('PAYPAL_MODE', $paypal_mode), //live||sandbox
                        'http.ConnectionTimeOut' => 30,
                        'log.LogEnabled' => true,
                        'log.FileName' => storage_path() . '/logs/paypal.log',
                        'log.LogLevel' => 'ERROR'
                    ),
                );
                Config::set('paypal', $config);
            }

            if (data_get($data,'flutterwave',null)) {
                $config = array(
                    'publicKey' => env('FLW_PUBLIC_KEY',data_get($data,'flutterwave.public_key',null)), // values : (local | production)
                    'secretKey' => env('FLW_SECRET_KEY', data_get($data,'flutterwave.secret_key',null)),
                    'secretHash' => env('FLW_SECRET_HASH', data_get($data,'flutterwave.hash',null)),
                );
                Config::set('flutterwave', $config);
            }

            if (data_get($data,'razor_pay',null)) {
                $config = array(
                    'razor_key' => env('RAZOR_KEY', data_get($data,'razor_pay.api_key',null)),
                    'razor_secret' => env('RAZOR_SECRET', data_get($data,'razor_pay.api_secret',null))
                );
                Config::set('razor', $config);
            }

            if (data_get($data,'paytm',null)) {
                $PAYTM_STATUS_QUERY_NEW_URL='https://securegw-stage.paytm.in/merchant-status/getTxnStatus';
                $PAYTM_TXN_URL='https://securegw-stage.paytm.in/theia/processTransaction';
                if (data_get($data,'paytm.mode',null) == 'live') {
                    $PAYTM_STATUS_QUERY_NEW_URL='https://securegw.paytm.in/merchant-status/getTxnStatus';
                    $PAYTM_TXN_URL='https://securegw.paytm.in/theia/processTransaction';
                }
                $config = array(
                    'PAYTM_ENVIRONMENT' => ($mode=='live')?'PROD':'TEST',
                    'PAYTM_MERCHANT_KEY' => env('PAYTM_MERCHANT_KEY',data_get($data,'paytm.merchant_key',null)),
                    'PAYTM_MERCHANT_MID' => env('PAYTM_MERCHANT_MID', data_get($data,'paytm.merchant_id',null)),
                    'PAYTM_MERCHANT_WEBSITE' => env('PAYTM_MERCHANT_WEBSITE', data_get($data,'paytm.merchant_website_link',null)),
                    'PAYTM_REFUND_URL' => env('PAYTM_REFUND_URL', data_get($data,'paytm.paytm_refund_url',null)),
                    'PAYTM_STATUS_QUERY_URL' => env('PAYTM_STATUS_QUERY_URL', $PAYTM_STATUS_QUERY_NEW_URL),
                    'PAYTM_STATUS_QUERY_NEW_URL' => env('PAYTM_STATUS_QUERY_NEW_URL', $PAYTM_STATUS_QUERY_NEW_URL),
                    'PAYTM_TXN_URL' => env('PAYTM_TXN_URL', $PAYTM_TXN_URL),
                );

                Config::set('config_paytm', $config);
            }

            Config::set('order_delivery_verification', $bs['order_delivery_verification'] ?? 0);
            Config::set('default_pagination', $bs['default_pagination'] ?? 25);
            Config::set('round_up_to_digit', $bs['digit_after_decimal_point'] ?? 2);
            Config::set('dm_maximum_orders', $bs['dm_maximum_orders'] ?? 1);
            Config::set('order_confirmation_model', $bs['order_confirmation_model'] ?? 'deliveryman');

            if (! empty($bs['timezone'])) {
                Config::set('timezone', $bs['timezone']);
                date_default_timezone_set($bs['timezone']);
            }

            Config::set('timeformat', (($bs['timeformat'] ?? null) == '12') ? 'h:i:a' : 'H:i');

            if (array_key_exists('canceled_by_store', $bs)) {
                Config::set('canceled_by_store', (boolean) $bs['canceled_by_store']);
            }
            if (array_key_exists('canceled_by_deliveryman', $bs)) {
                Config::set('canceled_by_deliveryman', (boolean) $bs['canceled_by_deliveryman']);
            }

            Config::set('toggle_veg_non_veg', (boolean) ($bs['toggle_veg_non_veg'] ?? false));

            $credentials = ! empty($bs['s3_credential']) ? json_decode($bs['s3_credential'], true) : null;
            $config = (boolean) ($bs['local_storage'] ?? false);
            if ($credentials) {
                Config::set('filesystems.default', $config ? ($config == 0 ? 's3' : 'local') : 'local');
                Config::set('filesystems.disks.s3.key', $credentials['key']);
                Config::set('filesystems.disks.s3.secret', $credentials['secret']);
                Config::set('filesystems.disks.s3.region', $credentials['region']);
                Config::set('filesystems.disks.s3.bucket', $credentials['bucket']);
                Config::set('filesystems.disks.s3.url', $credentials['url']);
                Config::set('filesystems.disks.s3.endpoint', $credentials['end_point']);
            }

        } catch (\Exception $ex) {
        }
    }
}
