<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'grocery', 'as' => 'grocery.', 'middleware' => ['localization']], function () {
    Route::group(['prefix' => 'provider', 'middleware' => ['vendor.api']], function () {
        Route::get('products/list', [\Modules\Grocery\Http\Controllers\Api\Provider\GroceryController::class, 'productList']);
    });

    Route::group(['middleware' => 'module-check'], function () {
        Route::get('products/list', [\Modules\Grocery\Http\Controllers\Api\Public\GroceryController::class, 'productList']);
    });
});
