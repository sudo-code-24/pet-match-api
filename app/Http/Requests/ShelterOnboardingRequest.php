<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelterOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'ein_tax_id' => ['nullable', 'string', 'max:255'],
            'physical_address' => ['required', 'string', 'max:2000'],
            'bio_mission' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'verification_docs' => ['nullable', 'array'],
            'verification_docs.*' => ['file', 'mimes:pdf,doc,docx', 'max:10240'],
            'shelter_type' => ['required', Rule::in(['Rescue', 'Municipal', 'Sanctuary'])],
            'max_capacity' => ['nullable', 'integer', 'min:0'],
            'facilities' => ['nullable'],
            'operating_hours' => ['nullable'],
            'services_offered' => ['nullable'],
            'adoption_requirements' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}

