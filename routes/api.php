<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rotas públicas
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Webhook MercadoPago (público — MP não tem auth com nosso app)
Route::post('/webhooks/mercadopago', [WebhookController::class, 'mercadopago']);

// Rotas protegidas (usuário logado)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::delete('/me', [AuthController::class, 'destroy']);
    Route::get('/me/exportar', [AuthController::class, 'exportarDados']);

    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'destroy']);

    // Validação de cupom (cliente no checkout) — throttle contra brute force
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon'])
        ->middleware('throttle:10,1');

    Route::post('/payments/preference', [PaymentController::class, 'criarPreference']);

    // Rotas restritas a administradores
    Route::middleware('admin')->group(function () {
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('coupons', CouponController::class);
    });
});