<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class DiscoverableUserResource extends JsonResource
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

        /** @var UserService $userService */
        $userService = app(UserService::class);
        $petSummary = $userService->buildPetSummary($user);

        $latitude = null;
        $longitude = null;
        $address = $profile?->address;
        if ($address !== null) {
            $lat = $address->latitude;
            $lon = $address->longitude;
            if ($lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon)) {
                $latitude = (float) $lat;
                $longitude = (float) $lon;
            }
        }

        return [
            'id' => $user->id,
            'name' => $name,
            'profile_image' => $profile?->avatar_url ?? null,
            'bio' => $bioOut,
            'pet_summary' => $petSummary,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }
}
