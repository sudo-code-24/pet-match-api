<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;
        $viewerId = $request->user()->id;

        $other = $conversation->user_one_id === $viewerId
            ? $conversation->userTwo
            : $conversation->userOne;

        $last = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'other_user' => $other instanceof User
                ? new ChatUserSummaryResource($other)
                : null,
            'last_message' => $last instanceof Message
                ? [
                    'id' => $last->id,
                    'message' => $last->message,
                    'sender_id' => $last->sender_id,
                    'created_at' => $last->created_at?->toIso8601String(),
                ]
                : null,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
