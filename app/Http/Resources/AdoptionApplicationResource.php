<?php

namespace App\Http\Resources;

use App\Models\AdoptionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdoptionApplication */
class AdoptionApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'petId' => $this->pet_id,
            'applicantUserId' => $this->applicant_user_id,
            'message' => $this->message,
            'status' => $this->status,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
