<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StorePetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', Rule::in(['dog', 'cat'])],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'breed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'health_notes' => ['sometimes', 'nullable', 'string'],
            'adoption_details' => ['sometimes', 'nullable', 'string'],
            'purpose' => ['sometimes', Rule::in(['adoption', 'mate', 'companion'])],
            'image_url' => ['sometimes', 'required_without:image_urls', 'string', 'max:2048'],
            'image_urls' => ['sometimes', 'required_without:image_url', 'array', 'min:1', 'max:3'],
            'image_urls.*' => ['string', 'max:2048'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
