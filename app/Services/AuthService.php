<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Shelter;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @param  array{
     *   role: 'foster'|'shelter',
     *   first_name: string,
     *   last_name: string,
     *   shelter_name?: string|null,
     *   profile?: array<string, mixed>,
     *   shelter_profile?: array<string, mixed>,
     *   email: string,
     *   password: string
     * }  $data
     * @return array{user: array{id: string, email: string, role: string}, profile: ?UserProfile, shelter: ?Shelter, token: string}
     */
    public function register(array $data): array
    {
        $firstName = trim((string) $data['first_name']);
        $lastName = trim((string) $data['last_name']);
        $fullName = trim(sprintf('%s %s', $firstName, $lastName));

        $user = User::query()->create([
            'role' => $data['role'],
            'name' => $fullName !== '' ? $fullName : $data['email'],
            'email' => strtolower(trim((string) $data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        $profilePayload = $data['profile'] ?? [];
        $profile = UserProfile::query()->create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar_url' => $this->nullableString($profilePayload['avatar_url'] ?? null),
            'bio' => $profilePayload['bio'] ?? null,
            'address_id' => $this->createAddressFromPayload($profilePayload['address'] ?? null)?->id,
        ]);

        $shelter = null;
        if ($data['role'] === 'shelter') {
            $shelterPayload = $data['shelter_profile'] ?? [];
            $shelter = Shelter::query()->create([
                'user_id' => $user->id,
                'shelter_name' => $data['shelter_name'] ?? 'Unnamed Shelter',
                'description' => $shelterPayload['description'] ?? null,
                'address' => $shelterPayload['address'] ?? null,
                'address_id' => $this->createAddressFromPayload($shelterPayload['address_details'] ?? null)?->id,
                'phone' => $shelterPayload['phone'] ?? null,
                'verification_status' => 'pending',
            ]);
        }

        $profile->loadMissing('address');
        $shelter?->loadMissing('addressRecord');

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile' => $profile,
            'shelter' => $shelter,
            'token' => $user->createToken('api-token')->plainTextToken,
        ];
    }

    /**
     * @param  array{email: string, password: string}  $data
     * @return array{user: array{id: string, email: string, role: string}, profile: ?UserProfile, shelter: ?Shelter, token: string}
     *
     * @throws AuthenticationException
     */
    public function login(array $data): array
    {
        $user = User::query()->with(['profile.address', 'shelter.addressRecord'])->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile' => $user->profile,
            'shelter' => $user->shelter,
            'token' => $user->createToken('api-token')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function createAddressFromPayload(?array $payload): ?Address
    {
        if ($payload === null) {
            return null;
        }

        $addressData = [
            'street' => $this->nullableString($payload['street'] ?? null),
            'landmark' => $this->nullableString($payload['landmark'] ?? null),
            'barangay' => $this->nullableString($payload['barangay'] ?? null),
            'city' => $this->nullableString($payload['city'] ?? null),
            'province' => $this->nullableString($payload['province'] ?? null),
            'zip_code' => $this->nullableString($payload['zip_code'] ?? null),
            'country' => $this->nullableString($payload['country'] ?? null),
            'latitude' => isset($payload['latitude']) && is_numeric($payload['latitude']) ? (float) $payload['latitude'] : null,
            'longitude' => isset($payload['longitude']) && is_numeric($payload['longitude']) ? (float) $payload['longitude'] : null,
        ];

        $addressData['full_address'] = $this->nullableString($payload['full_address'] ?? null)
            ?? $this->composeFullAddress($addressData);

        if (
            $addressData['street'] === null
            && $addressData['landmark'] === null
            && $addressData['barangay'] === null
            && $addressData['city'] === null
            && $addressData['province'] === null
            && $addressData['zip_code'] === null
            && $addressData['country'] === null
            && $addressData['latitude'] === null
            && $addressData['longitude'] === null
        ) {
            return null;
        }

        return Address::query()->create($addressData);
    }

    /**
     * @param  array{street: ?string, landmark: ?string, barangay: ?string, city: ?string, province: ?string, zip_code: ?string, country: ?string}  $addressData
     */
    private function composeFullAddress(array $addressData): ?string
    {
        $parts = array_filter([
            $addressData['street'],
            $addressData['landmark'],
            $addressData['barangay'],
            $addressData['city'],
            $addressData['province'],
            $addressData['zip_code'],
            $addressData['country'],
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
