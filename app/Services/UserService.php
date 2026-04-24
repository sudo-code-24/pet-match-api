<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserService
{
    /**
     * Paginated discoverable user profiles (visibility enforced server-side only).
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function discoverDiscoverableUsers(
        string $excludeUserId,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        return User::query()
            ->select('users.*')
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->where('user_profiles.is_discoverable', true)
            ->where('users.id', '!=', $excludeUserId)
            ->orderByDesc('user_profiles.updated_at')
            ->with([
                'userProfile.address',
                'pets' => static function ($query): void {
                    $query->select('id', 'user_id', 'name', 'species', 'purpose')
                        ->orderBy('name')
                        ->limit(12);
                },
            ])
            ->paginate($perPage, ['users.*'], 'page', $page);
    }

    /**
     * Short human-readable line for active pets (optional).
     */
    /**
     * Public profile for another user, only when their profile is discoverable.
     */
    public function findDiscoverablePublicUser(string $viewerId, string $subjectUserId): ?User
    {
        if ($viewerId === $subjectUserId) {
            return null;
        }

        return User::query()
            ->whereKey($subjectUserId)
            ->whereHas('userProfile', static function ($query): void {
                $query->where('is_discoverable', true);
            })
            ->with([
                'userProfile.address',
                'pets',
            ])
            ->first();
    }

    public function buildPetSummary(User $user): ?string
    {
        /** @var Collection<int, Pet> $pets */
        $pets = $user->pets;
        $count = $pets->count();
        if ($count === 0) {
            return null;
        }

        $names = $pets->take(3)->pluck('name')->implode(', ');
        $ellipsis = $count > 3 ? '…' : '';

        return sprintf(
            '%d %s · %s%s',
            $count,
            $count === 1 ? 'pet' : 'pets',
            $names,
            $ellipsis
        );
    }
}
