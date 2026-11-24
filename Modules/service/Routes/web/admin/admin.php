<?php

/*
|--------------------------------------------------------------------------
| Web Routes - Service Admin Panel
|--------------------------------------------------------------------------
|
| Routes for admin to manage agro-service categories, providers, and bookings.
|
*/

Route::group(['prefix' => 'service', 'as' => 'service.'], function () {
    Route::get('/dashboard', [\Modules\Service\Http\Controllers\Web\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('/categories', \Modules\Service\Http\Controllers\Web\Admin\ServiceCategoryController::class);
    Route::resource('/providers', \Modules\Service\Http\Controllers\Web\Admin\ServiceProviderController::class);
    Route::resource('/bookings', \Modules\Service\Http\Controllers\Web\Admin\ServiceBookingController::class);
    Route::get('/reports', [\Modules\Service\Http\Controllers\Web\Admin\ReportController::class, 'index'])->name('reports');
});
