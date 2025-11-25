<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });
    });

    // categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/tree', [CategoryController::class, 'tree']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    // products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/featured', [ProductController::class, 'featured']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/related', [ProductController::class, 'related']);

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        // User profile routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [AuthController::class, 'user']);
            // Route::put('/', [ProfileController::class, 'update']);
            // Route::put('/password', [ProfileController::class, 'updatePassword']);
        });

        // Admin only routes
        Route::middleware('admin')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::patch('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
            Route::patch('products/{product}/images/{image}', [ProductController::class, 'updateImage']);
        });
    });

    // Public routes (no authentication required)
    // Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    // Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
});

Route::fallback(function () {
    return response()->json([
        'code' => 404,
        'message' => 'Resource not found'
    ])->setStatusCode(404);
});
