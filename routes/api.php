<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\DB;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/ping', fn () => ['ok' => true, 'db' => DB::select('select 1 as x')]);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('clients', ClientController::class)
        ->only(['index', 'show']);

    Route::apiResource('invoices', InvoiceController::class)
        ->only(['index', 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('clients', ClientController::class)
            ->only(['store', 'update', 'destroy']);

        Route::apiResource('invoices', InvoiceController::class)
            ->only(['store', 'update', 'destroy']);
    });
});