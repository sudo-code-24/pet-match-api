<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class DiscoverableOwnerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->userProfile;

        $name = '';
        if ($profile) {
            $name = trim(((string) $profile->first_name).' '.((string) $profile->last_name));
        }
        if ($name === '') {
            $name = (string) ($user->email ?? 'User');
        }

        $bio = $profile && is_string($profile->bio) ? trim($profile->bio) : '';
        $bioOut = $bio !== '' ? $bio : null;

        $city = null;
        if ($profile?->address) {
            $cityRaw = trim((string) $profile->address->city);
            $city = $cityRaw !== '' ? $cityRaw : null;
        }

        return [
            'id' => $user->id,
            'name' => $name,
            'profile_image' => $profile?->avatar_url ?? null,
            'bio' => $bioOut,
            'city' => $city,
            'member_since' => $user->created_at?->format('M Y'),
            'pets' => PetResource::collection($user->pets),
        ];
    }
}
