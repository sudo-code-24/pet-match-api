<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitAdoptionApplicationRequest;
use App\Http\Resources\AdoptionApplicationResource;
use App\Models\Pet;
use App\Services\AdoptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class AdoptionController extends Controller
{
    public function __construct(
        private readonly AdoptionService $adoptionService,
    ) {
    }

    #[OA\Get(
        path: "/api/pets/adoption",
        tags: ["Adoption"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(type: "object")
                        ),
                        new OA\Property(property: "page", type: "string"),
                        new OA\Property(property: "limit", type: "string"),
                        new OA\Property(property: "total", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $pets = $this->adoptionService->listAdoptionPets([
            'species' => $request->query('species'),
            'breed' => $request->query('breed'),
            'gender' => $request->query('gender'),
            'age' => $request->query('age'),
            'size' => $request->query('size'),
            'location' => $request->query('location'),
            'search' => $request->query('search'),
            'page' => (int) $request->query('page', 1),
            'limit' => (int) $request->query('limit', 20),
        ]);

        return response()->json([
            'success' => true,
            'data' => collect($pets->items())
                ->map(fn (Pet $pet): array => $this->mapPetForAdoption($pet))
                ->all(),
            'page' => $pets->currentPage(),
            'limit' => $pets->perPage(),
            'total' => $pets->total(),
        ]);
    }

    #[OA\Get(
        path: "/api/pets/adoption/{id}",
        tags: ["Adoption"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(property: "data", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $pet = $this->adoptionService->getAdoptionPetById($id);
        if (! $pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapPetForAdoption($pet),
        ]);
    }

    #[OA\Post(
        path: "/api/adoptions/apply",
        tags: ["Adoption"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "SubmitAdoptionApplicationRequest",
                properties: [
                    new OA\Property(property: "petId", type: "string"),
                    new OA\Property(property: "applicantUserId", type: "string"),
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "submittedAt", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(property: "data", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function apply(SubmitAdoptionApplicationRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();
        if ((string) $validated['applicantUserId'] !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant user does not match the authenticated user.',
            ], 403);
        }

        try {
            $application = $this->adoptionService->submitApplication($validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => new AdoptionApplicationResource($application),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPetForAdoption(Pet $pet): array
    {
        $pet->loadMissing(['user.userProfile.address', 'user.userShelter.addressRecord']);

        $owner = $pet->user;
        $profile = $owner?->userProfile;
        $shelter = $owner?->userShelter;
        $source = ($owner?->role ?? 'foster') === 'shelter' ? 'shelter' : 'individual';
        $address = $shelter?->addressRecord ?? $profile?->address;
        $ownerName = trim((string) ($shelter?->shelter_name ?? ''));
        if ($ownerName === '') {
            $ownerName = trim(((string) ($profile?->first_name ?? '')).' '.((string) ($profile?->last_name ?? '')));
        }
        if ($ownerName === '') {
            $ownerName = (string) ($owner?->name ?? $owner?->email ?? 'Pet owner');
        }

        $species = strtolower((string) $pet->species) === 'cat' ? 'Cats' : 'Dogs';

        return [
            'id' => $pet->id,
            'name' => $pet->name,
            'age' => (float) ($pet->age ?? 0),
            'gender' => strtolower((string) $pet->gender) === 'female' ? 'Female' : 'Male',
            'breed' => (string) ($pet->breed ?? 'Mixed'),
            'imageUrl' => is_string($pet->image_url) ? $pet->image_url : '',
            'isUrgent' => false,
            'source' => $source,
            'category' => $species,
            'latitude' => $address?->latitude !== null ? (float) $address->latitude : null,
            'longitude' => $address?->longitude !== null ? (float) $address->longitude : null,
            'postedAt' => $pet->created_at?->getTimestampMs(),
            'description' => $pet->adoption_details,
            'organizationName' => $ownerName,
            'posterOwnerId' => $owner?->id,
            'ownerUserId' => $owner?->id,
        ];
    }
}
