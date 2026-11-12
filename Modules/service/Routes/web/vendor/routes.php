<?php

/*
|--------------------------------------------------------------------------
| Web Routes - Service Vendor Panel
|--------------------------------------------------------------------------
|
| Routes for agro-service providers to manage their offerings and bookings.
|
*/

Route::group(['prefix' => 'service', 'as' => 'vendor.service.'], function () {
    Route::get('/dashboard', [\Modules\Service\Http\Controllers\Web\Provider\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('/offerings', \Modules\Service\Http\Controllers\Web\Provider\ServiceOfferingController::class);
    Route::resource('/bookings', \Modules\Service\Http\Controllers\Web\Provider\ServiceBookingController::class);
});
