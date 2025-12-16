<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Business Type Controller
    Route::put('business-types-archived/{id}', [BusinessTypeController::class, 'archived']);
    Route::resource("business-types", BusinessTypeController::class);

    // User Controller
    Route::put('user-archived/{id}', [UserController::class, 'archived']);
    Route::resource("user", UserController::class);

    Route::post('logout', [AuthController::class, 'logout']);
});
