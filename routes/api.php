<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParentApiController; // <--- Pastikan ini ada!
use App\Http\Controllers\Integration\FinanceStudentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 1. PUBLIC ROUTES (Login & Webhook)
// Ini jalur yang lu tembak di Postman
Route::post('/login', [ParentApiController::class, 'login'])->middleware('throttle:5,1');
Route::post('/midtrans-callback', [ParentApiController::class, 'callback']);

// 2. PROTECTED ROUTES (Harus Punya Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/home', [ParentApiController::class, 'getHomeData']);
    Route::post('/payment/create', [ParentApiController::class, 'createPayment']);
});

// 3. TU-STUDENT-READ-CONTRACT — READ-ONLY student lookup for the Finance
//    backend (Tabungan). Machine-to-machine ONLY: requests pass the
//    finance.hmac middleware (certified TU-FINANCE HMAC spec) BEFORE any
//    processing. No session, no Sanctum token, no browser access. Throttled
//    to the same budget as the Finance-side outbound client conventions
//    (60 req/min). student_uuid is the sole cross-system identity.
Route::middleware(['finance.hmac', 'throttle:60,1'])
    ->prefix('integration/finance/students')
    ->name('integration.finance.students.')
    ->group(function () {
        Route::get('/', [FinanceStudentController::class, 'index'])->name('index');
        Route::get('/{student_uuid}', [FinanceStudentController::class, 'show'])->name('show');
    });