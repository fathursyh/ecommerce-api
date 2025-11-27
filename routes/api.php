<?php

use Illuminate\Support\Facades\Route;

include __DIR__ . '/auth.php';
include __DIR__ . '/category.php';
include __DIR__ . '/product.php';
include __DIR__ . '/cart.php';

Route::fallback(function () {
    return response()->json([
        'code' => 404,
        'message' => 'Resource not found'
    ])->setStatusCode(404);
});
