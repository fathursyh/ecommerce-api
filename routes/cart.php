<?php

use App\Http\Controllers\api\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Cart routes (accessible to both guest and authenticated users)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('/items/{cartItem}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/sync', [CartController::class, 'sync']);

        // Authenticated only
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/merge', [CartController::class, 'merge']);
        });
    });
});