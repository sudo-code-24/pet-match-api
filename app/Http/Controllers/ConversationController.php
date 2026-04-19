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

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly SocketBridgeService $socketBridge,
    ) {
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

        $conversations = $this->conversationService->listConversationsForUser($userId);

        return response()->json([
            'success' => true,
            'data' => ConversationResource::collection($conversations),
        ]);
    }

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
