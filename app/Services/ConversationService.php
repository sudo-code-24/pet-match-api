<?php

namespace App\Services;

use App\Jobs\SendExpoChatPushJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConversationService
{
    public function __construct(
        private readonly SocketBridgeService $socketBridge,
    ) {
    }
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

        $conversation = Conversation::query()->firstOrCreate(
            [
                'user_one_id' => $one,
                'user_two_id' => $two,
            ],
            [],
        );

        $cleared = false;
        if ($conversation->user_one_id === $currentUserId && $conversation->deleted_by_user_one) {
            $conversation->deleted_by_user_one = false;
            $cleared = true;
        }
        if ($conversation->user_two_id === $currentUserId && $conversation->deleted_by_user_two) {
            $conversation->deleted_by_user_two = false;
            $cleared = true;
        }
        if ($cleared) {
            if (! $conversation->deleted_by_user_one || ! $conversation->deleted_by_user_two) {
                $conversation->deleted_at = null;
            }
            $conversation->save();
        }

        return $conversation;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function listConversationsForUser(string $userId): Collection
    {
        return Conversation::query()
            ->where(function ($query) use ($userId): void {
                $query->where(function ($inner) use ($userId): void {
                    $inner->where('user_one_id', $userId)
                        ->where('deleted_by_user_one', false);
                })->orWhere(function ($inner) use ($userId): void {
                    $inner->where('user_two_id', $userId)
                        ->where('deleted_by_user_two', false);
                });
            })
            ->with([
                'latestMessage.sender.userProfile',
                'userOne.userProfile',
                'userTwo.userProfile',
            ])
            ->withCount([
                'messages as unread_count' => function ($query) use ($userId): void {
                    $query->where('sender_id', '!=', $userId)
                        ->whereNull('read_at');
                },
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findConversationForUser(string $conversationId, string $userId): ?Conversation
    {
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->where(function ($query) use ($userId): void {
                $query->where(function ($inner) use ($userId): void {
                    $inner->where('user_one_id', $userId)
                        ->where('deleted_by_user_one', false);
                })->orWhere(function ($inner) use ($userId): void {
                    $inner->where('user_two_id', $userId)
                        ->where('deleted_by_user_two', false);
                });
            })
            ->first();

        return $conversation;
    }

    /**
     * Cursor-paginated messages (newest first in each batch).
     *
     * Cursors are message ids. "Older than cursor" is defined by (created_at, id) tuple so
     * pagination stays chronological; UUID ids alone are not time-ordered.
     *
     * @return array{messages: Collection<int, Message>, next_cursor: ?string, has_more: bool}
     */
    public function listMessagesPageForUser(
        string $conversationId,
        string $userId,
        ?string $cursor,
        int $limit = 30,
    ): array {
        $conversation = $this->findConversationForUser($conversationId, $userId);
        if (! $conversation) {
            throw new InvalidArgumentException('Conversation not found.');
        }

        $pageSize = max(1, min($limit, 100));
        $fetch = $pageSize + 1;

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(['sender.userProfile'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor !== null && $cursor !== '') {
            $cursorMessage = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($cursor)
                ->first();

            if (! $cursorMessage) {
                throw new InvalidArgumentException('Invalid cursor.');
            }

            $query->where(function ($q) use ($cursorMessage): void {
                $q->where('created_at', '<', $cursorMessage->created_at)
                    ->orWhere(function ($inner) use ($cursorMessage): void {
                        $inner->where('created_at', '=', $cursorMessage->created_at)
                            ->where('id', '<', $cursorMessage->id);
                    });
            });
        }

        $rows = $query->limit($fetch)->get();
        $hasMore = $rows->count() > $pageSize;
        $messages = $rows->take($pageSize)->values();

        $nextCursor = null;
        if ($messages->isNotEmpty()) {
            /** @var Message $oldestInBatch */
            $oldestInBatch = $messages->last();
            $nextCursor = $hasMore ? $oldestInBatch->id : null;
        }

        return [
            'messages' => $messages,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
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

        $message = DB::transaction(function () use ($conversation, $senderId, $trimmed): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create([
                'sender_id' => $senderId,
                'message' => $trimmed,
            ]);

            $conversation->touch();

            return $message->load(['sender.userProfile']);
        });

        $this->notifyReceiverIfOffline($message, $conversation, $senderId);

        return $message;
    }

    public function softDeleteConversationForUser(string $conversationId, string $userId): void
    {
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->where(function ($query) use ($userId): void {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->first();

        if (! $conversation) {
            throw new InvalidArgumentException('Conversation not found.');
        }

        if ($conversation->user_one_id === $userId) {
            $conversation->deleted_by_user_one = true;
        } elseif ($conversation->user_two_id === $userId) {
            $conversation->deleted_by_user_two = true;
        } else {
            throw new InvalidArgumentException('Conversation not found.');
        }

        if ($conversation->deleted_by_user_one && $conversation->deleted_by_user_two) {
            $conversation->deleted_at = now();
        }

        $conversation->save();
    }

    /**
     * @return array{conversation_id: string, reader_id: string, read_at: string, message_ids: array<int, string>}
     */
    public function markPeerMessagesRead(string $conversationId, string $readerId): array
    {
        $conversation = $this->findConversationForUser($conversationId, $readerId);
        if (! $conversation) {
            throw new InvalidArgumentException('Conversation not found.');
        }

        $now = now();
        $ids = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $readerId)
            ->whereNull('read_at')
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            Message::query()->whereIn('id', $ids)->update(['read_at' => $now]);
        }

        return [
            'conversation_id' => $conversation->id,
            'reader_id' => $readerId,
            'read_at' => $now->toIso8601String(),
            'message_ids' => $ids,
        ];
    }

    private function notifyReceiverIfOffline(Message $message, Conversation $conversation, string $senderId): void
    {
        $receiverId = $conversation->user_one_id === $senderId
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        if ($this->socketBridge->isUserOnline($receiverId)) {
            return;
        }

        $tokens = UserDevice::query()
            ->where('user_id', $receiverId)
            ->pluck('device_token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $sender = $message->sender;
        $profile = $sender?->userProfile;
        $name = '';
        if ($profile) {
            $name = trim(((string) $profile->first_name).' '.((string) $profile->last_name));
        }
        if ($name === '') {
            $name = (string) ($sender?->email ?? 'Someone');
        }

        $preview = mb_strlen($message->message) > 120
            ? mb_substr($message->message, 0, 117).'...'
            : $message->message;

        $avatarUrl = $profile?->avatar_url;
        $avatarUrl = is_string($avatarUrl) && $avatarUrl !== '' ? $avatarUrl : null;

        SendExpoChatPushJob::dispatchAfterResponse(
            $receiverId,
            $name,
            $preview,
            [
                'type' => 'chat_message',
                'notificationType' => 'chat',
                'conversationId' => $conversation->id,
                'senderId' => $senderId,
                'senderName' => $name,
                'senderAvatarUrl' => $avatarUrl ?? '',
            ],
        );
    }
}
