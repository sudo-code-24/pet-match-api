<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePhotoRequest extends FormRequest
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
            'photo' => ['required', 'file', 'image', 'max:5120'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}
