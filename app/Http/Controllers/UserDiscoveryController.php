<?php

namespace App\Http\Controllers;

use App\Http\Resources\DiscoverableOwnerProfileResource;
use App\Http\Resources\DiscoverableUserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDiscoveryController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
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
