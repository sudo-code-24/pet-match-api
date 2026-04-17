<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class UpdatePetRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'species' => ['sometimes', Rule::in(['dog', 'cat'])],
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'breed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'health_notes' => ['sometimes', 'nullable', 'string'],
            'adoption_details' => ['sometimes', 'nullable', 'string'],
            'purpose' => ['sometimes', Rule::in(['adoption', 'mate', 'companion'])],
            'active' => ['sometimes', 'boolean'],
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
