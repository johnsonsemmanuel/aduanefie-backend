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
    Route::post('stores/{storeId}/verify', [MarketerPortalController::class, 'verifyStore'])->name('marketer.stores.verify');
    Route::get('commissions', [MarketerPortalController::class, 'commissions'])->name('marketer.commissions');
    Route::get('payouts', [MarketerPortalController::class, 'payouts'])->name('marketer.payouts');
    Route::get('payouts/request', [MarketerPortalController::class, 'showPayoutRequest'])->name('marketer.payouts.request');
    Route::post('payouts', [MarketerPortalController::class, 'requestPayout'])->name('marketer.payouts.store');
});
