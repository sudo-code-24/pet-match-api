<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversationService)
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

        $limit = (int) $request->query('limit', 100);

        try {
            $messages = $this->conversationService->listMessagesForUser($id, $userId, $limit);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => MessageResource::collection($messages),
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

    private function authenticatedUserId(Request $request): ?string
    {
        $user = $request->user();
        if (! $user || ! isset($user->id) || ! is_string($user->id) || $user->id === '') {
            return null;
        }

        return $user->id;
    }
}
