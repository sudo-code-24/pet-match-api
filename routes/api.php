<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\UserDiscoveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Anonymous access is required for authentication bootstrap (register,
| login, email existence probe) and for static image serving. Every other
| endpoint must live inside the authenticated group below.
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/email/exists', [AuthController::class, 'checkEmailExists']);

Route::get('/avatars/{path}', [AuthController::class, 'serveAvatar'])
    ->where('path', '.*');
Route::get('/pets/{path}', [PetController::class, 'servePetImages'])
    ->where('path', '.*');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
|
| Protected by Sanctum bearer tokens. Any route that reads or mutates
| user-owned data belongs here.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    // Session
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users/discover', [UserDiscoveryController::class, 'index']);
    Route::get('/users/{userId}/public-profile', [UserDiscoveryController::class, 'show']);

    // Profile
    Route::prefix('profile')->group(function (): void {
        Route::get('/details', [AuthController::class, 'profileDetails']);
        Route::put('/update', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::patch('/visibility', [AuthController::class, 'updateVisibility']);
    });

    // Uploads
    Route::post('/uploads/profile-photo', [AuthController::class, 'uploadProfilePhoto']);

    // Pets
    Route::prefix('pets')->controller(PetController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/upload-images', 'uploadImages');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
    Route::get('/pet/{id}', [PetController::class, 'show']);

    // 1:1 chat (REST only; no WebSockets)
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'storeMessage']);
});
