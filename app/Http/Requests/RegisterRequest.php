<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:foster,shelter'],
            'first_name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z]+$/'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z]+(?: [A-Za-z]+)*$/'],
            'shelter_name' => ['nullable', 'string', 'max:255', 'required_if:role,shelter'],
            'profile' => ['nullable', 'array'],
            'profile.bio' => ['nullable', 'string', 'max:2000'],
            'profile.pet_experience' => ['nullable', 'in:beginner,intermediate,expert'],
            'profile.house_type' => ['nullable', 'in:apartment,house,farm'],
            'profile.address' => ['nullable', 'array'],
            'profile.address.street' => ['nullable', 'string', 'max:255'],
            'profile.address.landmark' => ['nullable', 'string', 'max:255'],
            'profile.address.barangay' => ['nullable', 'string', 'max:255'],
            'profile.address.city' => ['nullable', 'string', 'max:255'],
            'profile.address.province' => ['nullable', 'string', 'max:255'],
            'profile.address.zip_code' => ['nullable', 'string', 'max:30'],
            'profile.address.country' => ['nullable', 'string', 'max:255'],
            'profile.address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'profile.address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'profile.address.full_address' => ['nullable', 'string', 'max:2000'],
            'shelter_profile' => ['nullable', 'array'],
            'shelter_profile.description' => ['nullable', 'string', 'max:2000'],
            'shelter_profile.address' => ['nullable', 'string', 'max:2000'],
            'shelter_profile.address_details' => ['nullable', 'array'],
            'shelter_profile.address_details.street' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.landmark' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.barangay' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.city' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.province' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.zip_code' => ['nullable', 'string', 'max:30'],
            'shelter_profile.address_details.country' => ['nullable', 'string', 'max:255'],
            'shelter_profile.address_details.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'shelter_profile.address_details.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'shelter_profile.address_details.full_address' => ['nullable', 'string', 'max:2000'],
            'shelter_profile.phone' => ['nullable', 'string', 'max:50'],
            'shelter_profile.contact_number' => ['nullable', 'string', 'max:50'],
            'shelter_profile.website' => ['nullable', 'url', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.*' => 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');
        $role = $this->input('role');
        $shelterName = $this->input('shelter_name');
        $profile = $this->input('profile', []);
        $shelterProfile = $this->input('shelter_profile', []);
        $profileAddress = $profile['address'] ?? [];
        $shelterAddressDetails = $shelterProfile['address_details'] ?? [];
        $legacyName = $this->input('name');

        if ((empty($firstName) || empty($lastName)) && is_string($legacyName)) {
            $parts = preg_split('/\s+/', trim($legacyName)) ?: [];
            if (empty($firstName) && isset($parts[0])) {
                $firstName = $parts[0];
            }
            if (empty($lastName) && count($parts) > 1) {
                $lastName = implode(' ', array_slice($parts, 1));
            }
        }

        $normalizedFirstName = $this->normalizeNamePart($firstName, false);
        $normalizedLastName = $this->normalizeNamePart($lastName, true);
        $normalizedShelterName = $this->normalizeText($shelterName, true, true);

        $this->merge([
            'role' => in_array($role, ['foster', 'shelter'], true) ? $role : 'foster',
            'first_name' => $normalizedFirstName,
            'last_name' => $normalizedLastName,
            'shelter_name' => $normalizedShelterName,
            'profile' => [
                'bio' => $this->normalizeText($profile['bio'] ?? '', true, false),
                'pet_experience' => $profile['pet_experience'] ?? null,
                'house_type' => $profile['house_type'] ?? null,
                'address' => $this->normalizeAddressPayload($profileAddress),
            ],
            'shelter_profile' => [
                'description' => $this->normalizeText($shelterProfile['description'] ?? '', true, false),
                'address' => $this->normalizeText($shelterProfile['address'] ?? '', true, false),
                'address_details' => $this->normalizeAddressPayload($shelterAddressDetails),
                'phone' => $this->normalizeText(
                    $shelterProfile['phone'] ?? ($shelterProfile['contact_number'] ?? ''),
                    false,
                    false,
                ),
                'website' => $this->normalizeText($shelterProfile['website'] ?? '', false, false),
            ],
        ]);
    }

    private function normalizeNamePart(mixed $value, bool $allowSpaces): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/', $allowSpaces ? ' ' : '', $value) ?? '');
        $normalized = strtolower($normalized);

        return ucwords($normalized);
    }

    private function normalizeText(mixed $value, bool $allowSpaces, bool $titleCase): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/', $allowSpaces ? ' ' : '', $value) ?? '');
        if ($titleCase) {
            return ucwords(strtolower($normalized));
        }

        return $normalized;
    }

    /**
     * @return array{
     *   street: string,
     *   landmark: string,
     *   barangay: string,
     *   city: string,
     *   province: string,
     *   zip_code: string,
     *   country: string,
     *   latitude: float|null,
     *   longitude: float|null,
     *   full_address: string
     * }
     */
    private function normalizeAddressPayload(mixed $address): array
    {
        $payload = is_array($address) ? $address : [];

        return [
            'street' => $this->normalizeText($payload['street'] ?? '', true, false),
            'landmark' => $this->normalizeText($payload['landmark'] ?? '', true, false),
            'barangay' => $this->normalizeText($payload['barangay'] ?? '', true, false),
            'city' => $this->normalizeText($payload['city'] ?? '', true, true),
            'province' => $this->normalizeText($payload['province'] ?? '', true, true),
            'zip_code' => $this->normalizeText($payload['zip_code'] ?? '', false, false),
            'country' => $this->normalizeText($payload['country'] ?? '', true, true),
            'latitude' => is_numeric($payload['latitude'] ?? null) ? (float) $payload['latitude'] : null,
            'longitude' => is_numeric($payload['longitude'] ?? null) ? (float) $payload['longitude'] : null,
            'full_address' => $this->normalizeText($payload['full_address'] ?? '', true, false),
        ];
    }
}
