<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyAdminController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/ping', fn () => ['ok' => true, 'db' => DB::select('select 1 as x')]);

// Public endpoints
Route::post('/register-company', [TenantController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login']);

// Super admin only
Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('/companies', [CompanyAdminController::class, 'index']);
    Route::post('/companies/{company}/approve', [CompanyAdminController::class, 'approve']);
    Route::post('/companies/{company}/reject', [CompanyAdminController::class, 'reject']);
    Route::post('/companies/{company}/suspend', [CompanyAdminController::class, 'suspend']);
    Route::post('/companies/{company}/reactivate', [CompanyAdminController::class, 'reactivate']);
    Route::post('/companies/{company}/pending', [CompanyAdminController::class, 'markPending']);
});

// Authenticated tenant routes (requires active company)
Route::middleware(['auth:sanctum', 'company.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Viewers and Admins
    Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);

    // Company Admins only
    Route::middleware('admin')->group(function () {
        Route::apiResource('clients', ClientController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('invoices', InvoiceController::class)->only(['store', 'update', 'destroy']);

        // Team User Management
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
    });
});
