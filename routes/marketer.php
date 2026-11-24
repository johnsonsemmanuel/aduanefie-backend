<?php

use App\Http\Controllers\MarketerAuthController;
use App\Http\Controllers\MarketerMfaController;
use App\Http\Controllers\MarketerPortalController;
use App\Http\Middleware\MarketerAuthenticated;
use Illuminate\Support\Facades\Route;

/*
| Remote-marketer portal (partners.aduanefie.com).
|
| RouteServiceProvider already wraps this file in the `web` group. Paths are
| denied by config/portals.php on store, logistics and api hosts, where they
| must 404 rather than redirect.
*/
Route::get('marketer-login', [MarketerAuthController::class, 'showLogin'])->name('marketer.login');
Route::post('marketer-login', [MarketerAuthController::class, 'login'])
    ->name('marketer.login.submit')
    ->middleware('throttle:marketer-login');
Route::get('marketer-forgot-password', [MarketerAuthController::class, 'showForgotPassword'])->name('marketer.forgot');
Route::post('marketer-forgot-password', [MarketerAuthController::class, 'sendLoginLink'])
    ->name('marketer.forgot.send')
    ->middleware('throttle:marketer-forgot');
Route::get('marketer-magic-login/{id}', [MarketerAuthController::class, 'magicLogin'])
    ->name('marketer.magic-login')
    ->middleware('signed');
Route::post('marketer-logout', [MarketerAuthController::class, 'logout'])->name('marketer.logout');

Route::get('marketer-mfa', [MarketerMfaController::class, 'showChallenge'])->name('marketer.mfa.challenge');
Route::post('marketer-mfa', [MarketerMfaController::class, 'verify'])
    ->name('marketer.mfa.verify')
    ->middleware('throttle:admin-mfa');
Route::get('marketer-mfa/setup', [MarketerMfaController::class, 'showSetup'])->name('marketer.mfa.setup');
Route::post('marketer-mfa/setup', [MarketerMfaController::class, 'confirmSetup'])
    ->name('marketer.mfa.setup.submit')
    ->middleware('throttle:admin-mfa');
Route::get('marketer-mfa/recovery-codes', [MarketerMfaController::class, 'recoveryCodes'])
    ->name('marketer.mfa.recovery-codes');

Route::middleware(MarketerAuthenticated::class)->prefix('marketer')->group(function () {
    Route::get('/', [MarketerPortalController::class, 'dashboard'])->name('marketer.dashboard');
    Route::get('register-farmer', [MarketerPortalController::class, 'showRegisterFarmer'])->name('marketer.register-farmer');
    Route::post('register-farmer', [MarketerPortalController::class, 'storeRegisterFarmer'])->name('marketer.register-farmer.store');
    Route::post('stores/{storeId}/verify', [MarketerPortalController::class, 'verifyStore'])->name('marketer.stores.verify');
    Route::get('backyard-farms', [MarketerPortalController::class, 'backyardFarms'])->name('marketer.backyard-farms');
    Route::post('backyard-farms/{id}/verify', [MarketerPortalController::class, 'verifyBackyardFarm'])->name('marketer.backyard-farms.verify');
    Route::get('regional-tours', [MarketerPortalController::class, 'regionalTours'])->name('marketer.regional-tours');
    Route::post('regional-tours/registrations/{id}/status', [MarketerPortalController::class, 'updateTourRegistrationStatus'])->name('marketer.regional-tours.status');
    Route::get('commissions', [MarketerPortalController::class, 'commissions'])->name('marketer.commissions');
    Route::get('payouts', [MarketerPortalController::class, 'payouts'])->name('marketer.payouts');
    Route::get('payouts/request', [MarketerPortalController::class, 'showPayoutRequest'])->name('marketer.payouts.request');
    Route::post('payouts', [MarketerPortalController::class, 'requestPayout'])->name('marketer.payouts.store');
    Route::post('password', [MarketerAuthController::class, 'updatePassword'])->name('marketer.password.update');
});
