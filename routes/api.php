<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\GearCategoryController;
use App\Http\Controllers\Api\GearController;
use App\Http\Controllers\Api\GearVariantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Index (GET /api) — health + endpoint map, so the base URL is not 404
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return response()->json([
        'name' => 'Kembara.id API',
        'description' => 'Sistem rental & booking perlengkapan outdoor (Final Project FWD).',
        'version' => '1.0.0',
        'status' => 'ok',
        'authentication' => 'Bearer token (Laravel Sanctum). Header: Authorization: Bearer <token>',
        'roles' => ['admin', 'customer'],
        'documentation' => 'API_DOCUMENTATION.md (repo) & Postman collection "Kembara.id API".',
        'response_envelope' => ['success' => 'bool', 'message' => 'string?', 'data' => 'mixed', 'meta' => 'pagination?'],
        'endpoints' => [
            'auth' => [
                'POST   /api/register',
                'POST   /api/login',
                'POST   /api/logout            (auth)',
                'GET    /api/profile           (auth)',
            ],
            'public_catalog' => [
                'GET    /api/gears',
                'GET    /api/gears/{id}',
                'GET    /api/gears/slug/{slug}',
                'GET    /api/categories',
                'GET    /api/categories/{id}',
            ],
            'customer' => [
                'POST   /api/bookings                  (auth)',
                'GET    /api/bookings                  (auth)',
                'GET    /api/bookings/{id}             (auth)',
                'POST   /api/bookings/{id}/payment     (auth)',
                'GET    /api/bookings/{id}/payment     (auth)',
            ],
            'admin' => [
                'POST   /api/admin/gears                        (admin)',
                'PUT    /api/admin/gears/{id}                   (admin)',
                'PATCH  /api/admin/gears/{id}/availability      (admin)',
                'DELETE /api/admin/gears/{id}                   (admin, hard delete)',
                'POST   /api/admin/categories                (admin)',
                'PUT    /api/admin/categories/{id}           (admin)',
                'DELETE /api/admin/categories/{id}           (admin)',
                'GET    /api/admin/bookings                  (admin)',
                'PATCH  /api/admin/bookings/{id}/status      (admin)',
                'PATCH  /api/admin/bookings/{id}/verify      (admin)',
                'GET    /api/admin/reports/dashboard         (admin)',
                'GET    /api/admin/reports/popular-gear      (admin)',
                'GET    /api/admin/reports/revenue           (admin)',
                'GET    /api/admin/reports/low-stock         (admin)',
                'GET    /api/admin/reports/busiest-periods   (admin)',
                'GET    /api/admin/reports/status-breakdown  (admin)',
                'GET    /api/admin/reports/category-performance (admin)',
            ],
        ],
        'webhook' => 'POST /api/payments/webhook (Midtrans callback)',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Routes (Katalog, Categories, & Payment Webhook)
|--------------------------------------------------------------------------
*/
// Throttle auth endpoints to blunt credential brute-forcing (10 req/min per IP).
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/gears', [GearController::class, 'index']);
Route::get('/gears/{id}', [GearController::class, 'show'])->whereNumber('id');
Route::get('/gears/slug/{slug}', [GearController::class, 'showBySlug']);

Route::get('/categories', [GearCategoryController::class, 'index']);
Route::get('/categories/{id}', [GearCategoryController::class, 'show'])->whereNumber('id');

// Live delivery-fee preview from a pasted Google Maps link (public, throttled).
Route::post('/delivery/quote', [DeliveryController::class, 'quote'])->middleware('throttle:60,1');

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
        Route::patch('/gears/{id}/availability', [GearController::class, 'setAvailability'])->whereNumber('id');
        Route::delete('/gears/{id}', [GearController::class, 'destroy'])->whereNumber('id');

        // Gear Variant Management (size/color + per-variant stock)
        Route::post('/gears/{gearId}/variants', [GearVariantController::class, 'store'])->whereNumber('gearId');
        Route::put('/gears/{gearId}/variants/{variantId}', [GearVariantController::class, 'update'])->whereNumber(['gearId', 'variantId']);
        Route::delete('/gears/{gearId}/variants/{variantId}', [GearVariantController::class, 'destroy'])->whereNumber(['gearId', 'variantId']);

        // Delivery-fee Settings
        Route::get('/settings/delivery', [SettingController::class, 'delivery']);
        Route::put('/settings/delivery', [SettingController::class, 'updateDelivery']);

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
        Route::get('/reports/busiest-periods', [ReportController::class, 'busiestPeriods']);
        Route::get('/reports/status-breakdown', [ReportController::class, 'statusBreakdown']);
        Route::get('/reports/category-performance', [ReportController::class, 'categoryPerformance']);
    });
});
