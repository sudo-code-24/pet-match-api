<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/email/exists', [AuthController::class, 'checkEmailExists']);
Route::post('/uploads/profile-photo', [AuthController::class, 'uploadProfilePhoto']);
Route::get('/avatars/{path}', [AuthController::class, 'serveAvatar'])->where('path', '.*');
Route::get('/profile/details', [AuthController::class, 'profileDetails']);
Route::put('/profile/update', [AuthController::class, 'updateProfile']);
Route::post('/profile/change-password', [AuthController::class, 'changePassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
