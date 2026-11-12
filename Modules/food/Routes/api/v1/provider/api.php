<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'food', 'as' => 'food.', 'middleware' => ['localization']], function () {
    Route::group(['prefix' => 'provider', 'middleware' => ['vendor.api']], function () {
        Route::get('products/list', [\Modules\Food\Http\Controllers\Api\Provider\FoodController::class, 'productList']);
    });

    Route::group(['middleware' => 'module-check'], function () {
        Route::get('products/list', [\Modules\Food\Http\Controllers\Api\Public\FoodController::class, 'productList']);
    });
});
