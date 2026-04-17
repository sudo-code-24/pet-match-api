<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use App\Services\PetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function __construct(private readonly PetService $petService)
    {
    }

    public function store(StorePetRequest $request): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $pet = $this->petService->createPet($request->validated(), $userId);

        return response()->json([
            'success' => true,
            'data' => $this->formatPet($pet),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $active = $request->query('active', 'true');
        $filters = [
            'active' => filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'search' => $request->query('search'),
            'page' => (int) $request->query('page', 1),
            'limit' => (int) $request->query('limit', 10),
        ];

        $pets = $this->petService->getPets($userId, $filters);

        return response()->json([
            'success' => true,
            'data' => array_map(
                fn (Pet $pet): array => $this->formatPet($pet),
                $pets->items(),
            ),
            'page' => $pets->currentPage(),
            'limit' => $pets->perPage(),
            'total' => $pets->total(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $pet = $this->petService->getPetById($id, $userId);
        if (! $pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPet($pet),
        ]);
    }

    public function update(UpdatePetRequest $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $pet = $this->petService->updatePet($id, $userId, $request->validated());
        if (! $pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPet($pet),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $deleted = $this->petService->deletePet($id, $userId);
        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Pet deleted successfully',
            ],
        ]);
    }

    private function authenticatedUserId(Request $request): ?string
    {
        $user = $request->user();
        if (! $user || ! isset($user->id) || ! is_string($user->id) || $user->id === '') {
            return null;
        }

        return $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPet(Pet $pet): array
    {
        $imageUrls = is_array($pet->image_urls) ? $pet->image_urls : [];
        if ($imageUrls === [] && is_string($pet->image_url) && trim($pet->image_url) !== '') {
            $imageUrls = [trim($pet->image_url)];
        }

        return [
            'id' => $pet->id,
            'user_id' => $pet->user_id,
            'name' => $pet->name,
            'species' => $pet->species,
            'gender' => $pet->gender,
            'breed' => $pet->breed,
            'age' => $pet->age,
            'health_notes' => $pet->health_notes,
            'adoption_details' => $pet->adoption_details,
            'purpose' => $pet->purpose,
            'image_url' => $imageUrls[0] ?? $pet->image_url,
            'image_urls' => array_slice(array_values(array_unique($imageUrls)), 0, 3),
            'active' => (bool) $pet->active,
            'created_at' => $pet->created_at,
            'updated_at' => $pet->updated_at,
        ];
    }
}
