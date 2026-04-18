<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ChatUserSummaryResource extends JsonResource
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

        return [
            'id' => $user->id,
            'display_name' => $name !== '' ? $name : (string) ($user->email ?? 'User'),
            'avatar_url' => $profile->avatar_url ?? null,
        ];
    }
}
