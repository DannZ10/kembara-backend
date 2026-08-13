<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GearCategoryController;
use App\Http\Controllers\Api\GearController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Katalog, Categories, & Payment Webhook)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/gears', [GearController::class, 'index']);
Route::get('/gears/{id}', [GearController::class, 'show'])->whereNumber('id');
Route::get('/gears/slug/{slug}', [GearController::class, 'showBySlug']);

Route::get('/categories', [GearCategoryController::class, 'index']);
Route::get('/categories/{id}', [GearCategoryController::class, 'show'])->whereNumber('id');

// Midtrans Payment Webhook Callback
Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook']);

/*
|--------------------------------------------------------------------------
| Protected Customer / Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Customer Booking Routes
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);

    // Customer Payment Routes
    Route::post('/bookings/{id}/payment', [PaymentController::class, 'createPayment']);
    Route::get('/bookings/{id}/payment', [PaymentController::class, 'getPaymentStatus']);

    /*
    |--------------------------------------------------------------------------
    | Protected Admin Only Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('is.admin')->prefix('admin')->group(function () {
        // Gear Management
        Route::post('/gears', [GearController::class, 'store']);
        Route::put('/gears/{id}', [GearController::class, 'update'])->whereNumber('id');
        Route::delete('/gears/{id}', [GearController::class, 'destroy'])->whereNumber('id');

        // Category Management
        Route::post('/categories', [GearCategoryController::class, 'store']);
        Route::put('/categories/{id}', [GearCategoryController::class, 'update'])->whereNumber('id');
        Route::delete('/categories/{id}', [GearCategoryController::class, 'destroy'])->whereNumber('id');

        // Booking Management
        Route::get('/bookings', [BookingController::class, 'indexAdmin']);
        Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
        Route::patch('/bookings/{id}/verify', [BookingController::class, 'verifyIdentity']);

        // Admin Analytics & Reports
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/popular-gear', [ReportController::class, 'popularGear']);
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
        Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);
    });
});
