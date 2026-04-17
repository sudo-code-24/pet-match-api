<?php

namespace App\Services;

use App\Models\Pet;
use App\Repositories\PetRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PetService
{
    public function __construct(private readonly PetRepository $petRepository)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPet(array $data, string $userId): Pet
    {
        return $this->petRepository->create([
            'user_id' => $userId,
            'name' => trim((string) $data['name']),
            'species' => (string) $data['species'],
            'gender' => (string) $data['gender'],
            'breed' => $this->nullableString($data['breed'] ?? null),
            'age' => isset($data['age']) ? (int) $data['age'] : null,
            'health_notes' => $this->nullableString($data['health_notes'] ?? null),
            'adoption_details' => $this->nullableString($data['adoption_details'] ?? null),
            'purpose' => isset($data['purpose']) ? (string) $data['purpose'] : 'companion',
            'image_url' => null,
            'image_urls' => [],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);
    }

    /**
     * @param  array{active?: bool, search?: string|null, page?: int, limit?: int}  $filters
     */
    public function getPets(string $userId, array $filters): LengthAwarePaginator
    {
        return $this->petRepository->paginateByUser($userId, $filters);
    }

    public function getPetById(string $id, string $userId): ?Pet
    {
        return $this->petRepository->findOwnedByUser($id, $userId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePet(string $id, string $userId, array $data): ?Pet
    {
        $pet = $this->petRepository->findOwnedByUser($id, $userId);
        if (! $pet) {
            return null;
        }

        $pet->fill($this->normalizeUpdateData($data));
        $pet->save();

        return $pet->fresh();
    }

    /**
     * @param  array<int, string>  $imageUrls
     */
    public function replacePetImages(string $id, string $userId, array $imageUrls): ?Pet
    {
        $pet = $this->petRepository->findOwnedByUser($id, $userId);
        if (! $pet) {
            return null;
        }

        $normalized = array_slice(array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $imageUrls),
            static fn (string $value): bool => $value !== '',
        ))), 0, 3);

        $pet->image_urls = $normalized;
        $pet->image_url = $normalized[0] ?? null;
        $pet->save();

        return $pet->fresh();
    }

    public function deletePet(string $id, string $userId): bool
    {
        $pet = $this->petRepository->findOwnedByUser($id, $userId);
        if (! $pet) {
            return false;
        }

        $pet->active = false;
        $pet->save();

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeUpdateData(array $data): array
    {
        $normalized = [];
        if (array_key_exists('name', $data)) {
            $normalized['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('species', $data)) {
            $normalized['species'] = (string) $data['species'];
        }
        if (array_key_exists('gender', $data)) {
            $normalized['gender'] = (string) $data['gender'];
        }
        if (array_key_exists('breed', $data)) {
            $normalized['breed'] = $this->nullableString($data['breed']);
        }
        if (array_key_exists('age', $data)) {
            $normalized['age'] = $data['age'] === null ? null : (int) $data['age'];
        }
        if (array_key_exists('health_notes', $data)) {
            $normalized['health_notes'] = $this->nullableString($data['health_notes']);
        }
        if (array_key_exists('adoption_details', $data)) {
            $normalized['adoption_details'] = $this->nullableString($data['adoption_details']);
        }
        if (array_key_exists('purpose', $data)) {
            $normalized['purpose'] = (string) $data['purpose'];
        }
        if (array_key_exists('active', $data)) {
            $normalized['active'] = (bool) $data['active'];
        }

        return $normalized;
    }

}
