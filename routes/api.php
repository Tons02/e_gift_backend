<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\ExternalController;
use App\Http\Controllers\Api\InternalController;
use App\Http\Controllers\Api\OneChargingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Support\Facades\Route;


Route::post('login', [AuthController::class, 'login']);

Route::get('public-voucher-search', [VoucherController::class, 'public_voucher_search']);
Route::get('public-external-employee-voucher-search', [VoucherController::class, 'public_external_employee_voucher_search']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Business Type Controller
    Route::put('business-types-archived/{id}', [BusinessTypeController::class, 'archived']);
    Route::resource("business-types", BusinessTypeController::class);

    // One Charging Controller
    Route::resource("one-charging", OneChargingController::class);

    // User Controller
    Route::put('user-archived/{id}', [UserController::class, 'archived']);
    Route::resource("user", UserController::class);

    // Internal Controller
    Route::put('internal-customer-archived/{id}', [InternalController::class, 'archived']);
    Route::get('export-internal-customer-template', [InternalController::class, 'export_internal_customer_template']);
    Route::get('internal-customer-archived/{id}', [InternalController::class, 'archived']);
    Route::resource("internal-customer", InternalController::class);

    // External Controller
    Route::put('external-customer-archived/{id}', [ExternalController::class, 'archived']);
    Route::resource("external-customer", ExternalController::class);


    // Voucher Controller
    Route::get('voucher', [VoucherController::class, 'index']);
    Route::get('cashier-voucher-search', [VoucherController::class, 'cashier_voucher_search']);
    Route::patch('claim-voucher/{id}', [VoucherController::class, 'claimed_voucher']);
    Route::get('export-voucher', [VoucherController::class, 'export_voucher']);


    Route::post('logout', [AuthController::class, 'logout']);
});
