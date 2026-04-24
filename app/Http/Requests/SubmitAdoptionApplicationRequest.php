<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAdoptionApplicationRequest extends FormRequest
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
            'petId' => ['required', 'uuid', 'exists:pets,id'],
            'applicantUserId' => ['required', 'uuid', 'exists:users,id'],
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['required', 'string', 'max:60'],
            'submittedAt' => ['required', 'date'],
        ];
    }
}
