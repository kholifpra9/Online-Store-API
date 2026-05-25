<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
    Online Store API Routes
    All routes return JSON. No authentication is required for this assessment.
*/

Route::prefix('v1')->group(function () {

    // Products
    Route::get('products',      [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    // Orders
    Route::post('orders',           [OrderController::class, 'store']);
    Route::get('orders/{order}',    [OrderController::class, 'show']);

});