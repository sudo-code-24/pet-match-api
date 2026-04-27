<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Http\Resources\PetResource;
use App\Services\PetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use OpenApi\Attributes as OA;

class PetController extends Controller
{
    public function __construct(private readonly PetService $petService)
    {
    }

    #[OA\Post(
        path: "/api/pets",
        tags: ["Pet"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "StorePetRequest",
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "species", type: "string"),
                    new OA\Property(property: "gender", type: "string"),
                    new OA\Property(property: "breed", type: "string"),
                    new OA\Property(property: "age", type: "integer"),
                    new OA\Property(property: "health_notes", type: "string"),
                    new OA\Property(property: "adoption_details", type: "string"),
                    new OA\Property(property: "purpose", type: "string"),
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
            'data' => new PetResource($pet),
        ], 201);
    }

    #[OA\Get(
        path: "/api/pets",
        tags: ["Pet"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(property: "data", type: "string"),
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
            'data' => PetResource::collection($pets->items()),
            'page' => $pets->currentPage(),
            'limit' => $pets->perPage(),
            'total' => $pets->total(),
        ]);
    }

    #[OA\Post(
        path: "/api/pets/upload-images",
        tags: ["Pet"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RequestPayload",
                additionalProperties: true
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
    public function uploadImages(Request $request): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'petId' => ['required', 'string', 'uuid'],
            'images' => ['required', 'array', 'min:1', 'max:3'],
            'images.*' => ['required', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $petId = (string) $request->input('petId');
        $pet = $this->petService->getPetById($petId, $userId);
        if (! $pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        $uploadedUrls = [];
        foreach ((array) $request->file('images', []) as $image) {
            $extension = $image->guessExtension() ?: 'jpg';
            $path = $image->storeAs(
                'pets',
                Str::uuid().'.'.$extension,
                'public',
            );

            if (! is_string($path) || $path === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not upload pet images.',
                ], 422);
            }
            $uploadedUrls[] = $path;
        }

        $updatedPet = $this->petService->replacePetImages($petId, $userId, $uploadedUrls);
        if (! $updatedPet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PetResource($updatedPet),
        ]);
    }

    #[OA\Get(
        path: "/api/pet/{id}",
        tags: ["Pet"],
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
            'data' => new PetResource($pet),
        ]);
    }

    #[OA\Put(
        path: "/api/pets/{id}",
        tags: ["Pet"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "UpdatePetRequest",
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "species", type: "string"),
                    new OA\Property(property: "gender", type: "string"),
                    new OA\Property(property: "breed", type: "string"),
                    new OA\Property(property: "age", type: "integer"),
                    new OA\Property(property: "health_notes", type: "string"),
                    new OA\Property(property: "adoption_details", type: "string"),
                    new OA\Property(property: "purpose", type: "string"),
                    new OA\Property(property: "active", type: "boolean"),
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
            'data' => new PetResource($pet),
        ]);
    }

    #[OA\Delete(
        path: "/api/pets/{id}",
        tags: ["Pet"],
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
                        new OA\Property(property: "message", type: "string"),
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: "/api/pets/{path}",
        tags: ["Pet"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "path",
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
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", nullable: true, example: "Request processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            title: "ResponseData",
                            additionalProperties: true
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            nullable: true,
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ]
    )]
    public function servePetImages(string $path): BinaryFileResponse
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $storagePath = "pets/{$normalizedPath}";
        if (! Storage::disk('public')->exists($storagePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($storagePath));
    }
}
