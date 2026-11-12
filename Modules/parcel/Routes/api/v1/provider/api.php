<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'parcel', 'as' => 'parcel.', 'middleware' => ['localization']], function () {
    Route::group(['prefix' => 'provider', 'middleware' => ['vendor.api']], function () {
        Route::get('shipments/list', [\Modules\Parcel\Http\Controllers\Api\Provider\ParcelController::class, 'shipmentList']);
    });

    Route::group(['middleware' => 'module-check'], function () {
        Route::get('shipments/list', [\Modules\Parcel\Http\Controllers\Api\Public\ParcelController::class, 'shipmentList']);
    });
});
