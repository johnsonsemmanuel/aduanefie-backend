<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymobController;
use App\Http\Controllers\PaytabsController;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\SenangPayController;
use App\Http\Controllers\FlutterwaveV3Controller;
use App\Http\Controllers\PaypalPaymentController;
use App\Http\Controllers\StripePaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::post('/subscribeToTopic', [FirebaseController::class, 'subscribeToTopic']);
Route::get('/', 'HomeController@index')->name('home');
Route::get('lang/{locale}', 'HomeController@lang')->name('lang');
Route::get('terms-and-conditions', 'HomeController@terms_and_conditions')->name('terms-and-conditions');
Route::get('about-us', 'HomeController@about_us')->name('about-us');
Route::get('contact-us', 'HomeController@contact_us')->name('contact-us');
Route::post('send-message', 'HomeController@send_message')->name('send-message');
Route::get('privacy-policy', 'HomeController@privacy_policy')->name('privacy-policy');
Route::get('cancelation', 'HomeController@cancelation')->name('cancelation');
Route::get('refund', 'HomeController@refund_policy')->name('refund');
Route::get('shipping-policy', 'HomeController@shipping_policy')->name('shipping-policy');
Route::post('newsletter/subscribe', 'NewsletterController@newsLetterSubscribe')->name('newsletter.subscribe');
Route::get('subscription-invoice/{id}', 'HomeController@subscription_invoice')->name('subscription_invoice');
Route::get('order-invoice/{id}', 'HomeController@order_invoice')->name('order_invoice');

// Dedicated admin front door. Canonical URL is /admin-login; the legacy
// /login/<tab> entry stays available for store logins and old bookmarks.
Route::get('admin-login', 'AdminAuthController@showLogin')->name('admin.login');
Route::post('admin-login', 'AdminAuthController@login')
    ->name('admin.login.submit')
    ->middleware('throttle:admin-login');

// Second factor. These run before the panel is reachable and are deliberately
// outside the panel's own 'admin' middleware group: the admin is not signed in
// until the challenge is passed. Identity comes from the pending marker that
// AdminMfaService writes only after a password has been verified.
Route::get('admin-mfa', 'AdminMfaController@showChallenge')->name('admin.mfa.challenge');
Route::post('admin-mfa', 'AdminMfaController@verify')
    ->name('admin.mfa.verify')
    ->middleware('throttle:admin-mfa');
Route::get('admin-mfa/setup', 'AdminMfaController@showSetup')->name('admin.mfa.setup');
Route::post('admin-mfa/setup', 'AdminMfaController@confirmSetup')
    ->name('admin.mfa.setup.submit')
    ->middleware('throttle:admin-mfa');
Route::get('admin-mfa/recovery-codes', 'AdminMfaController@recoveryCodes')->name('admin.mfa.recovery-codes');

// Rider portal on logistics.aduanefie.com. Login is phone + password, matching
// the mobile API. Status updates are intentionally absent: see
// RiderDashboardController for why.
Route::get('rider-login', 'RiderAuthController@showLogin')->name('rider.login');
Route::post('rider-login', 'RiderAuthController@login')
    ->name('rider.login.submit')
    ->middleware('throttle:rider-login');
Route::post('rider-logout', 'RiderAuthController@logout')->name('rider.logout');

Route::middleware(\App\Http\Middleware\RiderAuthenticated::class)->prefix('rider')->group(function () {
    Route::get('/', 'RiderDashboardController@index')->name('rider.dashboard');
    Route::get('earnings', 'RiderDashboardController@earnings')->name('rider.earnings');
});

// Canonical store-management front door on admin.aduanefie.com. The legacy
// /login/store and /login/store-employee entries below stay as aliases so old
// bookmarks, the mobile app and DataSetting.store_login_url keep working.
Route::get('store-login', 'StoreAuthController@showLogin')->name('store.login');
Route::post('store-login', 'StoreAuthController@login')
    ->name('store.login.submit')
    ->middleware('throttle:store-login');
Route::get('store-login/employee', 'StoreAuthController@showEmployeeLogin')->name('store.employee.login');
Route::post('store-login/employee', 'StoreAuthController@loginEmployee')
    ->name('store.employee.login.submit')
    ->middleware('throttle:store-login');

Route::get('login/{tab}', 'LoginController@login')->name('login');
Route::post('external-login-from-drivemond', 'LoginController@externalLoginFromDrivemond');
Route::post('login_submit', 'LoginController@submit')->name('login_post')->middleware(['actch', 'throttle:web-login']);
Route::get('logout', 'LoginController@logout')->name('logout');
Route::get('/reset-password', 'LoginController@reset_password_request')->name('reset-password');
Route::post('/vendor-reset-password', 'LoginController@vendor_reset_password_request')->name('vendor-reset-password');
Route::get('/password-reset', 'LoginController@reset_password')->name('change-password');
Route::post('verify-otp', 'LoginController@verify_token')->name('verify-otp');
Route::post('reset-password-submit', 'LoginController@reset_password_submit')->name('reset-password-submit');
Route::get('otp-resent', 'LoginController@otp_resent')->name('otp_resent');

Route::get('authentication-failed', function () {
    $errors = [];
    array_push($errors, ['code' => 'auth-001', 'message' => 'Unauthenticated.']);
    return response()->json([
        'errors' => $errors,
    ], 401);
})->name('authentication-failed');

Route::group(['prefix' => 'payment-mobile'], function () {
    Route::get('/', 'PaymentController@payment')->name('payment-mobile');
    Route::get('set-payment-method/{name}', 'PaymentController@set_payment_method')->name('set-payment-method');
});

Route::get('payment-success', 'PaymentController@success')->name('payment-success');
Route::get('payment-fail', 'PaymentController@fail')->name('payment-fail');
Route::get('payment-cancel', 'PaymentController@cancel')->name('payment-cancel');

$is_published = 0;
try {
$full_data = include('Modules/Gateways/Addon/info.php');
$is_published = $full_data['is_published'] == 1 ? 1 : 0;
} catch (\Exception $exception) {}

if (!$is_published) {
    Route::group(['prefix' => 'payment'], function () {

        //STRIPE
        Route::group(['prefix' => 'stripe', 'as' => 'stripe.'], function () {
            Route::get('pay', [StripePaymentController::class, 'index'])->name('pay');
            Route::get('token', [StripePaymentController::class, 'payment_process_3d'])->name('token');
            Route::get('success', [StripePaymentController::class, 'success'])->name('success');
            Route::get('canceled', [StripePaymentController::class, 'canceled'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYPAL
        Route::group(['prefix' => 'paypal', 'as' => 'paypal.'], function () {
            Route::get('pay', [PaypalPaymentController::class, 'payment']);
            Route::any('success', [PaypalPaymentController::class, 'success'])->name('success')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);;
            Route::any('cancel', [PaypalPaymentController::class, 'cancel'])->name('cancel')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);;
        });

        //SENANG-PAY
        Route::group(['prefix' => 'senang-pay', 'as' => 'senang-pay.'], function () {
            Route::get('pay', [SenangPayController::class, 'index']);
            Route::any('callback', [SenangPayController::class, 'return_senang_pay']);
        });

        //FLUTTERWAVE
        Route::group(['prefix' => 'flutterwave-v3', 'as' => 'flutterwave-v3.'], function () {
            Route::get('pay', [FlutterwaveV3Controller::class, 'initialize'])->name('pay');
            Route::get('callback', [FlutterwaveV3Controller::class, 'callback'])->name('callback');
        });

        //PAYSTACK
        Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
            Route::get('pay', [PaystackController::class, 'index'])->name('pay');
            Route::post('payment', [PaystackController::class, 'redirectToGateway'])->name('payment');
            Route::get('callback', [PaystackController::class, 'handleGatewayCallback'])->name('callback');
        });

        //PAYMOB
        Route::group(['prefix' => 'paymob', 'as' => 'paymob.'], function () {
            Route::any('pay', [PaymobController::class, 'credit'])->name('pay');
            Route::any('callback', [PaymobController::class, 'callback'])->name('callback');
        });

        //PAYTABS
        Route::group(['prefix' => 'paytabs', 'as' => 'paytabs.'], function () {
            Route::any('pay', [PaytabsController::class, 'payment'])->name('pay');
            Route::any('callback', [PaytabsController::class, 'callback'])->name('callback');
            Route::any('response', [PaytabsController::class, 'response'])->name('response');
        });
    });
}


Route::get('/test', function () {
    dd('Hello tester');
});

Route::get('module-test', function () {
});

//Restaurant Registration
Route::group(['prefix' => 'vendor', 'as' => 'restaurant.'], function () {
    Route::get('apply', 'VendorController@create')->name('create');
    Route::post('apply', 'VendorController@store')->name('store');
    Route::get('get-all-modules', 'VendorController@get_all_modules')->name('get-all-modules');
    Route::get('get-module-type', 'VendorController@get_modules_type')->name('get-module-type');

    Route::get('back', 'VendorController@back')->name('back');
    Route::post('business-plan', 'VendorController@business_plan')->name('business_plan');
    Route::post('payment', 'VendorController@payment')->name('payment');
    Route::get('final-step', 'VendorController@final_step')->name('final_step');
});

//Deliveryman Registration
Route::group(['prefix' => 'deliveryman', 'as' => 'deliveryman.'], function () {
    Route::get('apply', 'DeliveryManController@create')->name('create');
    Route::post('apply', 'DeliveryManController@store')->name('store');
});
