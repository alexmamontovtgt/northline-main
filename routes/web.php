<?php

declare(strict_types=1);

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json($request->user()->only(['id', 'name', 'email']));
    });

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
});

Route::view('/{path?}', 'app')->where('path', '^(?!api(?:/|$)).*');
