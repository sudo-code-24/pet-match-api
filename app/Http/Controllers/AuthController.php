<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\CheckEmailRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadProfilePhotoRequest;
use App\Models\Address;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuthService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function serveAvatar(string $path): BinaryFileResponse
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
            ->with(['userProfile.address', 'userShelter.addressRecord'])
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
            'profile' => $user->userProfile,
            'shelter' => $user->userShelter,
        ];

        return response()->json($result);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['email']));

        $user = User::query()
            ->with(['userProfile.address'])
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Profile not found.',
            ], 404);
        }

        if ($email !== strtolower(trim((string) $user->email))) {
            return response()->json([
                'message' => 'Email cannot be changed.',
                'errors' => [
                    'email' => ['Email cannot be changed.'],
                ],
            ], 422);
        }

        /** @var UserProfile $profile */
        $profile = UserProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => '', 'last_name' => ''],
        );

        $requestedFirstName = trim((string) ($validated['first_name'] ?? ''));
        $requestedLastName = trim((string) ($validated['last_name'] ?? ''));
        $requestedName = trim(implode(' ', array_filter([
            $requestedFirstName,
            $requestedLastName,
        ])));
        $currentName = trim(implode(' ', array_filter([
            $profile->first_name,
            $profile->last_name,
        ])));
        $isNameChanged = $requestedName !== '' && strcasecmp($requestedName, $currentName) !== 0;

        if ($isNameChanged && $profile->last_name_updated_at) {
            $lastChangedAt = CarbonImmutable::parse($profile->last_name_updated_at);
            $nextAllowed = $lastChangedAt->addDays(30);
            if (CarbonImmutable::now()->lt($nextAllowed)) {
                return response()->json([
                    'message' => 'Name can only be updated once every 30 days.',
                    'errors' => [
                        'name' => ['Name can only be updated once every 30 days.'],
                    ],
                    'meta' => [
                        'next_name_update_at' => $nextAllowed->toIso8601String(),
                    ],
                ], 422);
            }
        }

        if ($isNameChanged) {
            $profile->first_name = $requestedFirstName;
            $profile->last_name = $requestedLastName;
            $profile->last_name_updated_at = CarbonImmutable::now();
        }

        $profile->bio = trim((string) ($validated['bio'] ?? ''));

        $incomingAddress = is_array($validated['address'] ?? null)
            ? $validated['address']
            : [];
        $hasAddressValue = collect([
            $incomingAddress['street'] ?? '',
            $incomingAddress['landmark'] ?? '',
            $incomingAddress['barangay'] ?? '',
            $incomingAddress['city'] ?? '',
            $incomingAddress['province'] ?? '',
            $incomingAddress['zip_code'] ?? '',
            $incomingAddress['country'] ?? '',
            $incomingAddress['full_address'] ?? '',
        ])->contains(fn ($value): bool => trim((string) $value) !== '');

        if ($hasAddressValue) {
            $address = $profile->address_id
                ? Address::query()->find($profile->address_id)
                : null;

            if (! $address) {
                $address = new Address();
            }

            $address->street = trim((string) ($incomingAddress['street'] ?? ''));
            $address->landmark = trim((string) ($incomingAddress['landmark'] ?? ''));
            $address->barangay = trim((string) ($incomingAddress['barangay'] ?? ''));
            $address->city = trim((string) ($incomingAddress['city'] ?? ''));
            $address->province = trim((string) ($incomingAddress['province'] ?? ''));
            $address->zip_code = trim((string) ($incomingAddress['zip_code'] ?? ''));
            $address->country = trim((string) ($incomingAddress['country'] ?? ''));
            $address->latitude = is_numeric($incomingAddress['latitude'] ?? null)
                ? (float) $incomingAddress['latitude']
                : null;
            $address->longitude = is_numeric($incomingAddress['longitude'] ?? null)
                ? (float) $incomingAddress['longitude']
                : null;
            $address->full_address = trim((string) ($incomingAddress['full_address'] ?? ''));
            $address->save();
            $profile->address_id = $address->id;
        } elseif ($profile->address_id) {
            $address = Address::query()->find($profile->address_id);
            if ($address) {
                $address->street = null;
                $address->landmark = null;
                $address->barangay = null;
                $address->city = null;
                $address->province = null;
                $address->zip_code = null;
                $address->country = null;
                $address->latitude = null;
                $address->longitude = null;
                $address->full_address = null;
                $address->save();
            }
        }

        $profile->save();
        $profile->loadMissing('address');

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile' => $profile,
            'meta' => [
                'next_name_update_at' => $profile->last_name_updated_at
                    ? CarbonImmutable::parse($profile->last_name_updated_at)->addDays(30)->toIso8601String()
                    : null,
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['email']));

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
                'errors' => [
                    'email' => ['User not found.'],
                ],
            ], 404);
        }

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        if (Hash::check((string) $validated['new_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password.',
                'errors' => [
                    'new_password' => ['New password must be different from current password.'],
                ],
            ], 422);
        }

        $user->password = (string) $validated['new_password'];
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->loadMissing(['userProfile.address', 'userShelter.addressRecord']);

        return response()->json([
            'user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ] : null,
            'profile' => $user?->userProfile,
            'shelter' => $user?->userShelter,
        ]);
    }
}
