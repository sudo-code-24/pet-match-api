<?php

namespace App\Repositories;

use App\Models\Pet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PetRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Pet
    {
        /** @var Pet $pet */
        $pet = Pet::query()->create($data);

        return $pet;
    }

    /**
     * @param  array{active?: bool, search?: string|null, page?: int, limit?: int}  $filters
     */
    public function paginateByUser(string $userId, array $filters): LengthAwarePaginator
    {
        $query = Pet::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if (array_key_exists('active', $filters)) {
            $query->where('active', (bool) $filters['active']);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $limit = max(1, (int) ($filters['limit'] ?? 10));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function findOwnedByUser(string $petId, string $userId): ?Pet
    {
        return Pet::query()
            ->where('id', $petId)
            ->where('user_id', $userId)
            ->first();
    }
}
