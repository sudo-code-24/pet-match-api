<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Services\ConversationService;
use App\Services\SocketBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly SocketBridgeService $socketBridge,
    ) {
    }

    #[OA\Get(
        path: "/api/conversations",
        tags: ["Conversation"],
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

        $conversations = $this->conversationService->listConversationsForUser($userId);

        return response()->json([
            'success' => true,
            'data' => ConversationResource::collection($conversations),
        ]);
    }

    #[OA\Post(
        path: "/api/conversations",
        tags: ["Conversation"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "StoreConversationRequest",
                properties: [
                    new OA\Property(property: "user_id", type: "string"),
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
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $otherUserId = (string) $request->validated('user_id');

        try {
            $conversation = $this->conversationService->createOrGetConversation($userId, $otherUserId);
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage() === 'User not found.' ? 404 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }

        $conversation->load(['userOne.userProfile', 'userTwo.userProfile', 'latestMessage.sender.userProfile']);

        return response()->json([
            'success' => true,
            'data' => new ConversationResource($conversation),
        ], 201);
    }

    #[OA\Get(
        path: "/api/conversations/{id}/messages",
        tags: ["Conversation"],
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
                        new OA\Property(property: "messages", type: "string"),
                        new OA\Property(property: "next_cursor", type: "string"),
                        new OA\Property(property: "has_more", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function messages(Request $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $limit = (int) $request->query('limit', 30);
        $cursorRaw = $request->query('cursor');
        $cursor = is_string($cursorRaw) && $cursorRaw !== '' ? $cursorRaw : null;

        try {
            $page = $this->conversationService->listMessagesPageForUser($id, $userId, $cursor, $limit);
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $status = match ($message) {
                'Invalid cursor.' => 422,
                default => 404,
            };

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => MessageResource::collection($page['messages']),
                'next_cursor' => $page['next_cursor'],
                'has_more' => $page['has_more'],
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/conversations/{id}/messages",
        tags: ["Conversation"],
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
                title: "StoreChatMessageRequest",
                properties: [
                    new OA\Property(property: "message", type: "string"),
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
    public function storeMessage(StoreChatMessageRequest $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $text = (string) $request->validated('message');

        try {
            $message = $this->conversationService->appendMessage($id, $userId, $text);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ], 201);
    }

    #[OA\Post(
        path: "/api/conversations/{id}/read",
        tags: ["Conversation"],
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
    public function markRead(Request $request, string $id): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $payload = $this->conversationService->markPeerMessagesRead($id, $userId);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        if ($payload['message_ids'] !== []) {
            $this->socketBridge->broadcast('messages_read', [
                'conversation_id' => $payload['conversation_id'],
                'reader_id' => $payload['reader_id'],
                'read_at' => $payload['read_at'],
                'message_ids' => $payload['message_ids'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    #[OA\Delete(
        path: "/api/conversations/{id}",
        tags: ["Conversation"],
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

        try {
            $this->conversationService->softDeleteConversationForUser($id, $userId);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        $this->socketBridge->broadcast('conversation_deleted', [
            'conversationId' => $id,
            'userId' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted.',
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
