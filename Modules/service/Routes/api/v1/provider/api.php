<?php

use Illuminate\Support\Facades\Route;

use Modules\Service\Http\Controllers\Api\Provider\ServiceController as ProviderServiceController;

/*
|--------------------------------------------------------------------------
| API Routes - Service Provider Panel
|--------------------------------------------------------------------------
|
| Routes for agro-service providers to manage their service offerings,
# schedules, and bookings.
|
*/

Route::group(['prefix' => 'service', 'as' => 'service.', 'middleware' => ['localization']], function () {

    Route::group(['prefix' => 'provider', 'namespace' => 'Provider', 'middleware' => ['vendor.api']], function () {

        // Service categories
        Route::group(['prefix' => 'category', 'as' => 'category.'], function () {
            Route::get('list', [ProviderServiceController::class, 'categoryList']);
            Route::post('create', [ProviderServiceController::class, 'createCategory']);
            Route::get('edit/{id}', [ProviderServiceController::class, 'editCategory']);
            Route::post('update/{id}', [ProviderServiceController::class, 'updateCategory']);
            Route::get('status/{id}', [ProviderServiceController::class, 'toggleCategoryStatus']);
            Route::delete('delete/{id}', [ProviderServiceController::class, 'deleteCategory']);
        });

        // Service offerings
        Route::group(['prefix' => 'offering', 'as' => 'offering.'], function () {
            Route::get('list', [ProviderServiceController::class, 'serviceList']);
            Route::get('edit/{id}', [ProviderServiceController::class, 'editService']);
            Route::post('create', [ProviderServiceController::class, 'createService']);
            Route::post('update/{id}', [ProviderServiceController::class, 'updateService']);
            Route::get('status/{id}', [ProviderServiceController::class, 'toggleServiceStatus']);
            Route::delete('delete/{id}', [ProviderServiceController::class, 'deleteService']);
        });

        // Service bookings
        Route::group(['prefix' => 'booking', 'as' => 'booking.'], function () {
            Route::get('list', [ProviderServiceController::class, 'bookingList']);
            Route::get('details/{id}', [ProviderServiceController::class, 'bookingDetails']);
            Route::post('accept/{id}', [ProviderServiceController::class, 'acceptBooking']);
            Route::post('complete/{id}', [ProviderServiceController::class, 'completeBooking']);
            Route::post('cancel/{id}', [ProviderServiceController::class, 'cancelBooking']);
        });

        // Provider profile
        Route::group(['prefix' => 'profile', 'as' => 'profile.'], function () {
            Route::get('/', [ProviderServiceController::class, 'profile']);
            Route::post('update', [ProviderServiceController::class, 'profileUpdate']);
        });

        Route::get('category/list/all', [ProviderServiceController::class, 'categoryListAll']);
    });
});
