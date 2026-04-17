<?php

namespace App\Http\Resources;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pet */
class PetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrls = is_array($this->image_urls) ? $this->image_urls : [];
        if ($imageUrls === [] && is_string($this->image_url) && trim($this->image_url) !== '') {
            $imageUrls = [trim($this->image_url)];
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'species' => $this->species,
            'gender' => $this->gender,
            'breed' => $this->breed,
            'age' => $this->age,
            'health_notes' => $this->health_notes,
            'adoption_details' => $this->adoption_details,
            'purpose' => $this->purpose,
            'image_url' => $imageUrls[0] ?? $this->image_url,
            'image_urls' => array_slice(array_values(array_unique($imageUrls)), 0, 3),
            'active' => (bool) $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
