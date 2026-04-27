<?php

namespace App\Http\Requests;

use App\Support\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'max:'.ImageUpload::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.max' => ImageUpload::MAX_ERROR_MESSAGE,
        ];
    }
}
