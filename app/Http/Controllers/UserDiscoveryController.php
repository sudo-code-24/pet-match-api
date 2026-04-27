<?php

namespace App\Http\Controllers;

use App\Http\Resources\DiscoverableOwnerProfileResource;
use App\Http\Resources\DiscoverableUserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserDiscoveryController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[OA\Get(
        path: "/api/users/discover",
        tags: ["UserDiscovery"],
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

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 15);

        $paginator = $this->userService->discoverDiscoverableUsers($userId, $page, $limit);

        return response()->json([
            'success' => true,
            'data' => DiscoverableUserResource::collection($paginator->items()),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    #[OA\Get(
        path: "/api/users/{userId}/public-profile",
        tags: ["UserDiscovery"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "userId",
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
    public function show(Request $request, string $userId): JsonResponse
    {
        $viewerId = $this->authenticatedUserId($request);
        if (! $viewerId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = $this->userService->findDiscoverablePublicUser($viewerId, $userId);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new DiscoverableOwnerProfileResource($user),
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
}
