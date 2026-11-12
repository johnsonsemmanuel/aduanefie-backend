<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'pharmacy', 'as' => 'pharmacy.', 'middleware' => ['localization']], function () {
    Route::group(['prefix' => 'provider', 'middleware' => ['vendor.api']], function () {
        Route::get('products/list', [\Modules\Pharmacy\Http\Controllers\Api\Provider\PharmacyController::class, 'productList']);
    });

    Route::group(['middleware' => 'module-check'], function () {
        Route::get('products/list', [\Modules\Pharmacy\Http\Controllers\Api\Public\PharmacyController::class, 'productList']);
    });
});
