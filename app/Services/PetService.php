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
        $imageUrls = $this->normalizeImageUrls($data);

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
            'image_url' => $imageUrls[0],
            'image_urls' => $imageUrls,
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

        if (array_key_exists('name', $data)) {
            $pet->name = trim((string) $data['name']);
        }
        if (array_key_exists('species', $data)) {
            $pet->species = (string) $data['species'];
        }
        if (array_key_exists('gender', $data)) {
            $pet->gender = (string) $data['gender'];
        }
        if (array_key_exists('breed', $data)) {
            $pet->breed = $this->nullableString($data['breed']);
        }
        if (array_key_exists('age', $data)) {
            $pet->age = $data['age'] === null ? null : (int) $data['age'];
        }
        if (array_key_exists('health_notes', $data)) {
            $pet->health_notes = $this->nullableString($data['health_notes']);
        }
        if (array_key_exists('adoption_details', $data)) {
            $pet->adoption_details = $this->nullableString($data['adoption_details']);
        }
        if (array_key_exists('purpose', $data)) {
            $pet->purpose = (string) $data['purpose'];
        }
        if (array_key_exists('image_url', $data)) {
            $pet->image_url = trim((string) $data['image_url']);
        }
        if (array_key_exists('image_urls', $data) || array_key_exists('image_url', $data)) {
            $imageUrls = $this->normalizeImageUrls($data);
            $pet->image_urls = $imageUrls;
            $pet->image_url = $imageUrls[0];
        }
        if (array_key_exists('active', $data)) {
            $pet->active = (bool) $data['active'];
        }

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
     * @return array<int, string>
     */
    private function normalizeImageUrls(array $data): array
    {
        $urls = [];

        if (isset($data['image_urls']) && is_array($data['image_urls'])) {
            $urls = array_values(array_filter(
                array_map(
                    static fn ($value): string => trim((string) $value),
                    $data['image_urls']
                ),
                static fn (string $value): bool => $value !== ''
            ));
        }

        if ($urls === [] && isset($data['image_url'])) {
            $legacy = trim((string) $data['image_url']);
            if ($legacy !== '') {
                $urls = [$legacy];
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, 3);
    }
}
