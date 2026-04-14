<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json($result);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->loadMissing(['profile.address', 'shelter.addressRecord']);

        return response()->json([
            'user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ] : null,
            'profile' => $user?->profile,
            'shelter' => $user?->shelter,
        ]);
    }
}
