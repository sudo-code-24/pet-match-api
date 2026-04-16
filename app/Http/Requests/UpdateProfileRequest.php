<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.landmark' => ['nullable', 'string', 'max:255'],
            'address.barangay' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.zip_code' => ['nullable', 'string', 'max:30'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address.full_address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address', []);
        $address = is_array($address) ? $address : [];

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'first_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('first_name')) ?? ''),
            'last_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('last_name')) ?? ''),
            'bio' => trim((string) $this->input('bio', '')),
            'address' => [
                'street' => trim((string) ($address['street'] ?? '')),
                'landmark' => trim((string) ($address['landmark'] ?? '')),
                'barangay' => trim((string) ($address['barangay'] ?? '')),
                'city' => trim((string) ($address['city'] ?? '')),
                'province' => trim((string) ($address['province'] ?? '')),
                'zip_code' => trim((string) ($address['zip_code'] ?? '')),
                'country' => trim((string) ($address['country'] ?? '')),
                'latitude' => is_numeric($address['latitude'] ?? null) ? (float) $address['latitude'] : null,
                'longitude' => is_numeric($address['longitude'] ?? null) ? (float) $address['longitude'] : null,
                'full_address' => trim((string) ($address['full_address'] ?? '')),
            ],
        ]);
    }
}
