<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/tokens', [AuthController::class, 'tokens']);
    Route::post('/revoke-all', [AuthController::class, 'revokeAll']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
});