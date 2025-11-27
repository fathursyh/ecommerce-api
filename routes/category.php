<?php

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Cart routes (accessible to both guest and authenticated users)
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/{category}', [CategoryController::class, 'show']);

        // Authenticated only
        Route::middleware('auth:sanctum')->group(function () {

        });

        // admin only
        Route::middleware('admin')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::patch('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        });
    });
});