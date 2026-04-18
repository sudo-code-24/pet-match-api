<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConversationService
{
    /**
     * @return array{0: string, 1: string}
     */
    public function normalizeUserPair(string $userA, string $userB): array
    {
        if (strcmp($userA, $userB) < 0) {
            return [$userA, $userB];
        }

        return [$userB, $userA];
    }

    public function createOrGetConversation(string $currentUserId, string $otherUserId): Conversation
    {
        if ($currentUserId === $otherUserId) {
            throw new InvalidArgumentException('Cannot start a conversation with yourself.');
        }

        if (! User::query()->whereKey($otherUserId)->exists()) {
            throw new InvalidArgumentException('User not found.');
        }

        [$one, $two] = $this->normalizeUserPair($currentUserId, $otherUserId);

        return Conversation::query()->firstOrCreate(
            [
                'user_one_id' => $one,
                'user_two_id' => $two,
            ],
            [],
        );
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function listConversationsForUser(string $userId): Collection
    {
        return Conversation::query()
            ->where(function ($query) use ($userId): void {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->with([
                'latestMessage.sender.userProfile',
                'userOne.userProfile',
                'userTwo.userProfile',
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findConversationForUser(string $conversationId, string $userId): ?Conversation
    {
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->where(function ($query) use ($userId): void {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->first();

        return $conversation;
    }

    /**
     * @return Collection<int, Message>
     */
    public function listMessagesForUser(string $conversationId, string $userId, int $limit = 100): Collection
    {
        $conversation = $this->findConversationForUser($conversationId, $userId);
        if (! $conversation) {
            throw new InvalidArgumentException('Conversation not found.');
        }

        $limit = max(1, min($limit, 500));

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(['sender.userProfile'])
            ->orderByDesc('created_at')
            // ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function appendMessage(string $conversationId, string $senderId, string $body): Message
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        $conversation = $this->findConversationForUser($conversationId, $senderId);
        if (! $conversation) {
            throw new InvalidArgumentException('Conversation not found.');
        }

        return DB::transaction(function () use ($conversation, $senderId, $trimmed): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create([
                'sender_id' => $senderId,
                'message' => $trimmed,
            ]);

            $conversation->touch();

            return $message->load(['sender.userProfile']);
        });
    }
}
