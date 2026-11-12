<?php

use Illuminate\Support\Facades\Route;

use Modules\Service\Http\Controllers\Api\Public\ServiceController as PublicServiceController;

/*
|--------------------------------------------------------------------------
| API Routes - Service Public (Customer)
|--------------------------------------------------------------------------
|
| Routes for customers to browse and book agro-services.
|
*/

Route::group(['prefix' => 'service', 'as' => 'service.', 'middleware' => ['localization']], function () {

    Route::group(['middleware' => 'module-check'], function () {
        // Browse service categories
        Route::get('category/list', [PublicServiceController::class, 'categoryList']);

        // Browse service offerings (with optional category filter)
        Route::get('offering/list', [PublicServiceController::class, 'serviceList']);
        Route::get('offering/details/{id}', [PublicServiceController::class, 'serviceDetails']);

        // Book a service
        Route::group(['prefix' => 'booking', 'middleware' => 'apiGuestCheck'], function () {
            Route::post('create', [PublicServiceController::class, 'createBooking']);
            Route::get('list', [PublicServiceController::class, 'bookingList']);
            Route::get('details/{id}', [PublicServiceController::class, 'bookingDetails']);
            Route::post('cancel/{id}', [PublicServiceController::class, 'cancelBooking']);
        });
    });
});
