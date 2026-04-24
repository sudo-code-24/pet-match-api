<?php

namespace App\Services;

use App\Models\AdoptionApplication;
use App\Models\Pet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AdoptionService
{
    /** @var array<int, string> */
    private const ACTIVE_APPLICATION_STATUSES = ['submitted', 'pending', 'in_review', 'approved'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitApplication(array $payload): AdoptionApplication
    {
        $petId = (string) $payload['petId'];
        $applicantUserId = (string) $payload['applicantUserId'];
        $status = trim((string) $payload['status']);
        $message = trim((string) $payload['message']);
        $submittedAt = Carbon::parse((string) $payload['submittedAt']);

        $alreadyActive = AdoptionApplication::query()
            ->where('pet_id', $petId)
            ->where('applicant_user_id', $applicantUserId)
            ->whereIn('status', self::ACTIVE_APPLICATION_STATUSES)
            ->exists();

        if ($alreadyActive) {
            throw new InvalidArgumentException('You already have an active application for this pet.');
        }

        /** @var AdoptionApplication $created */
        $created = AdoptionApplication::query()->create([
            'pet_id' => $petId,
            'applicant_user_id' => $applicantUserId,
            'message' => $message,
            'status' => $status === '' ? 'submitted' : $status,
            'submitted_at' => $submittedAt,
        ]);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listAdoptionPets(array $filters): LengthAwarePaginator
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 20), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = Pet::query()
            ->withoutGlobalScopes()
            ->where('active', true)
            ->where('purpose', 'adoption')
            ->with([
                'user.userProfile.address',
                'user.userShelter.addressRecord',
            ]);

        if (! empty($filters['species'])) {
            $query->where('species', strtolower((string) $filters['species']));
        }
        if (! empty($filters['breed'])) {
            $query->where('breed', 'ilike', '%'.trim((string) $filters['breed']).'%');
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', strtolower((string) $filters['gender']));
        }
        if (! empty($filters['age'])) {
            $this->applyAgeFilter($query, (string) $filters['age']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('breed', 'ilike', "%{$search}%")
                    ->orWhere('adoption_details', 'ilike', "%{$search}%")
                    ->orWhereHas('user.userProfile', function (Builder $profileQuery) use ($search): void {
                        $profileQuery
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('user.userProfile.address', function (Builder $addressQuery) use ($search): void {
                        $addressQuery->where('full_address', 'ilike', "%{$search}%");
                    });
            });
        }

        $location = trim((string) ($filters['location'] ?? ''));
        if ($location !== '') {
            $query->whereHas('user.userProfile.address', function (Builder $addressQuery) use ($location): void {
                $addressQuery->where('full_address', 'ilike', "%{$location}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($limit, ['*'], 'page', $page);
    }

    public function getAdoptionPetById(string $petId): ?Pet
    {
        return Pet::query()
            ->withoutGlobalScopes()
            ->where('active', true)
            ->where('purpose', 'adoption')
            ->with([
                'user.userProfile.address',
                'user.userShelter.addressRecord',
            ])
            ->find($petId);
    }

    private function applyAgeFilter(Builder $query, string $ageFilter): void
    {
        $normalized = strtolower(trim($ageFilter));
        if ($normalized === '') {
            return;
        }
        if (is_numeric($normalized)) {
            $query->where('age', '<=', (int) $normalized);
            return;
        }

        match ($normalized) {
            'young' => $query->whereNotNull('age')->where('age', '<=', 1),
            'adult' => $query->whereNotNull('age')->whereBetween('age', [2, 6]),
            'senior' => $query->whereNotNull('age')->where('age', '>=', 7),
            default => null,
        };
    }
}
