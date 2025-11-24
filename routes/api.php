<?php

use App\Http\Controllers\Api\AuthController;
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

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        // User profile routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [AuthController::class, 'user']);
            // Route::put('/', [ProfileController::class, 'update']);
            // Route::put('/password', [ProfileController::class, 'updatePassword']);
        });

        // Cart routes (example - you'll create these controllers later)
        // Route::apiResource('cart', CartController::class);

        // Order routes
        // Route::apiResource('orders', OrderController::class);

        // Wishlist routes
        // Route::apiResource('wishlist', WishlistController::class);

        // Address routes
        // Route::apiResource('addresses', AddressController::class);

        // Review routes
        // Route::post('products/{product}/reviews', [ReviewController::class, 'store']);

        // Admin only routes
        Route::middleware('admin')->group(function () {
            // Route::apiResource('admin/products', Admin\ProductController::class);
            // Route::apiResource('admin/categories', Admin\CategoryController::class);
            // Route::apiResource('admin/orders', Admin\OrderController::class);
        });
    });

    // Public routes (no authentication required)
    // Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    // Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
});

Route::fallback(function () {
    abort(404, 'API resource not found');
});
