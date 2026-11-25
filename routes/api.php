<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('register', 'register')->name('auth.register');
        Route::post('login', 'login')->name('auth.login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::get('me', 'me')->name('auth.me');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('dishes', DishController::class);

    Route::apiResource('tables', TableController::class)->only(['index', 'store', 'show']);
    Route::patch('tables/{table}/status', [TableController::class, 'updateStatus'])->name('tables.status');
});
