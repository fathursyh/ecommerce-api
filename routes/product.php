<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Cart routes (accessible to both guest and authenticated users)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/featured', [ProductController::class, 'featured']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::get('/{product}/related', [ProductController::class, 'related']);

        // Authenticated only
        Route::middleware('auth:sanctum')->group(function () {

        });

        // admin only
        Route::middleware('admin')->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::patch('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
            Route::patch('/{product}/images/{image}', [ProductController::class, 'updateImage']);
        });
    });
});