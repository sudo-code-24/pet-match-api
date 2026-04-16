<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\CheckEmailRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UploadProfilePhotoRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function checkEmailExists(CheckEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['email']));

        return response()->json([
            'exists' => User::query()->where('email', $email)->exists(),
        ]);
    }

    public function uploadProfilePhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $photo = $request->file('photo');
        $extension = $photo?->guessExtension() ?: 'jpg';
        $path = $photo?->storeAs(
            'avatars',
            Str::uuid().'.'.$extension,
            'public',
        );

        if (! is_string($path) || $path === '') {
            return response()->json(['message' => 'Could not upload profile photo.'], 422);
        }

        Storage::disk('public')->url($path);
        $userId = $validated['user_id'] ?? null;
        if (is_string($userId) && $userId !== '') {
            /** @var UserProfile $profile */
            $profile = UserProfile::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'first_name' => '',
                    'last_name' => '',
                ],
            );
            $profile->avatar_url = $path;
            $profile->save();
        }

        return response()->json([
            'url' => $path,
            'avatar_url' => $path,
        ]);
    }

    public function serveAvatar(string $path): Response
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $storagePath = "avatars/{$normalizedPath}";
        if (! Storage::disk('public')->exists($storagePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($storagePath));
    }

    public function profileDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
         $user = User::query()
            ->with(['profile.address', 'shelter.addressRecord'])
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }
        $result= [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile' => $user->profile,
            'shelter' => $user->shelter,
        ];

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
