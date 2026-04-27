<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\CheckEmailRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateVisibilityRequest;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    #[OA\Post(
        path: "/api/register",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RegisterRequest",
                properties: [
                    new OA\Property(property: "role", type: "string"),
                    new OA\Property(property: "first_name", type: "string"),
                    new OA\Property(property: "last_name", type: "string"),
                    new OA\Property(property: "shelter_name", type: "string"),
                    new OA\Property(
                        property: "profile",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "profile.avatar_url", type: "string"),
                    new OA\Property(property: "profile.bio", type: "string"),
                    new OA\Property(property: "profile.pet_experience", type: "string"),
                    new OA\Property(property: "profile.house_type", type: "string"),
                    new OA\Property(
                        property: "profile.address",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "profile.address.street", type: "string"),
                    new OA\Property(property: "profile.address.landmark", type: "string"),
                    new OA\Property(property: "profile.address.barangay", type: "string"),
                    new OA\Property(property: "profile.address.city", type: "string"),
                    new OA\Property(property: "profile.address.province", type: "string"),
                    new OA\Property(property: "profile.address.zip_code", type: "string"),
                    new OA\Property(property: "profile.address.country", type: "string"),
                    new OA\Property(property: "profile.address.latitude", type: "number"),
                    new OA\Property(property: "profile.address.longitude", type: "number"),
                    new OA\Property(property: "profile.address.full_address", type: "string"),
                    new OA\Property(
                        property: "shelter_profile",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "shelter_profile.description", type: "string"),
                    new OA\Property(property: "shelter_profile.address", type: "string"),
                    new OA\Property(
                        property: "shelter_profile.address_details",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "shelter_profile.address_details.street", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.landmark", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.barangay", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.city", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.province", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.zip_code", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.country", type: "string"),
                    new OA\Property(property: "shelter_profile.address_details.latitude", type: "number"),
                    new OA\Property(property: "shelter_profile.address_details.longitude", type: "number"),
                    new OA\Property(property: "shelter_profile.address_details.full_address", type: "string"),
                    new OA\Property(property: "shelter_profile.phone", type: "string"),
                    new OA\Property(property: "shelter_profile.contact_number", type: "string"),
                    new OA\Property(property: "shelter_profile.website", type: "string"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "password", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", nullable: true, example: "Request processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            title: "ResponseData",
                            additionalProperties: true
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            nullable: true,
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json($result, 201);
    }

    #[OA\Post(
        path: "/api/login",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "LoginRequest",
                properties: [
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "password", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json($result);
    }

    #[OA\Get(
        path: "/api/email/exists",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "exists", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function checkEmailExists(CheckEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['email']));

        return response()->json([
            'exists' => User::query()->where('email', $email)->exists(),
        ]);
    }

    #[OA\Post(
        path: "/api/uploads/profile-photo",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "UploadProfilePhotoRequest",
                properties: [
                    new OA\Property(property: "photo", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "url", type: "string"),
                        new OA\Property(property: "avatar_url", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function uploadProfilePhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $request->validated();
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

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        /** @var UserProfile $profile */
        $profile = UserProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => '',
                'last_name' => '',
            ],
        );
        $profile->avatar_url = $path;
        $profile->save();

        return response()->json([
            'url' => $path,
            'avatar_url' => $path,
        ]);
    }

    #[OA\Get(
        path: "/api/avatars/{path}",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "path",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", nullable: true, example: "Request processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            title: "ResponseData",
                            additionalProperties: true
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            nullable: true,
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: "/api/profile/details",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function profileDetails(Request $request): JsonResponse
    {
        $authenticatedUser = $request->user();
        if (! $authenticatedUser) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user = User::query()
            ->with(['userProfile.address', 'userShelter.addressRecord'])
            ->where('id', $authenticatedUser->id)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }
        $result = [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'push_notifications_enabled' => (bool) ($user->push_notifications_enabled ?? true),
            ],
            'profile' => $user->userProfile,
            'shelter' => $user->userShelter,
        ];

        return response()->json($result);
    }

    #[OA\Put(
        path: "/api/profile/update",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "UpdateProfileRequest",
                properties: [
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "first_name", type: "string"),
                    new OA\Property(property: "last_name", type: "string"),
                    new OA\Property(property: "bio", type: "string"),
                    new OA\Property(
                        property: "address",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "address.street", type: "string"),
                    new OA\Property(property: "address.landmark", type: "string"),
                    new OA\Property(property: "address.barangay", type: "string"),
                    new OA\Property(property: "address.city", type: "string"),
                    new OA\Property(property: "address.province", type: "string"),
                    new OA\Property(property: "address.zip_code", type: "string"),
                    new OA\Property(property: "address.country", type: "string"),
                    new OA\Property(property: "address.latitude", type: "number"),
                    new OA\Property(property: "address.longitude", type: "number"),
                    new OA\Property(property: "address.full_address", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "user", type: "string"),
                        new OA\Property(property: "id", type: "string"),
                        new OA\Property(property: "email", type: "string"),
                        new OA\Property(property: "role", type: "string"),
                        new OA\Property(property: "profile", type: "string"),
                        new OA\Property(property: "meta", type: "string"),
                        new OA\Property(property: "next_name_update_at", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $authenticatedUser = $request->user();
        if (! $authenticatedUser) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $email = strtolower(trim((string) $validated['email']));

        $user = User::query()
            ->with(['userProfile.address'])
            ->where('id', $authenticatedUser->id)
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

    #[OA\Post(
        path: "/api/profile/change-password",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "ChangePasswordRequest",
                properties: [
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "current_password", type: "string"),
                    new OA\Property(property: "new_password", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $authenticatedUser = $request->user();
        if (! $authenticatedUser) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $email = strtolower(trim((string) $validated['email']));

        $user = User::query()->where('id', $authenticatedUser->id)->first();
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

    #[OA\Patch(
        path: "/api/profile/visibility",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "UpdateVisibilityRequest",
                properties: [
                    new OA\Property(property: "is_discoverable", type: "boolean"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "user", type: "string"),
                        new OA\Property(property: "id", type: "string"),
                        new OA\Property(property: "email", type: "string"),
                        new OA\Property(property: "role", type: "string"),
                        new OA\Property(property: "profile", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function updateVisibility(UpdateVisibilityRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $isDiscoverable = (bool) $request->validated('is_discoverable');

        /** @var UserProfile $profile */
        $profile = UserProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => '', 'last_name' => ''],
        );

        $profile->is_discoverable = $isDiscoverable;
        $profile->save();
        $profile->loadMissing('address');

        return response()->json([
            'message' => 'Visibility updated successfully.',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile' => $profile,
        ]);
    }

    #[OA\Patch(
        path: "/api/profile/push-notifications",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RequestPayload",
                additionalProperties: true
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function updatePushNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user->push_notifications_enabled = (bool) $validator->validated()['enabled'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification preference updated.',
        ]);
    }

    #[OA\Post(
        path: "/api/logout",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RequestPayload",
                additionalProperties: true
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    #[OA\Get(
        path: "/api/me",
        tags: ["Auth"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "user", type: "string"),
                        new OA\Property(property: "id", type: "string"),
                        new OA\Property(property: "email", type: "string"),
                        new OA\Property(property: "role", type: "string"),
                        new OA\Property(property: "push_notifications_enabled", type: "boolean"),
                        new OA\Property(property: "profile", type: "string"),
                        new OA\Property(property: "shelter", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->loadMissing(['userProfile.address', 'userShelter.addressRecord']);

        return response()->json([
            'user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'push_notifications_enabled' => (bool) ($user->push_notifications_enabled ?? true),
            ] : null,
            'profile' => $user?->userProfile,
            'shelter' => $user?->userShelter,
        ]);
    }
}
