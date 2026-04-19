<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoPushService;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Delivers chat push after the HTTP response so message creation is never blocked.
 */
class SendExpoChatPushJob
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $receiverId,
        public string $title,
        public string $body,
        public array $data,
    ) {}

    public function handle(ExpoPushService $expoPush): void
    {
        try {
            /** @var User|null $receiver */
            $receiver = User::query()->find($this->receiverId);
            if (! $receiver) {
                return;
            }
            if (! $receiver->push_notifications_enabled) {
                return;
            }

            $tokens = UserDevice::query()
                ->where('user_id', $this->receiverId)
                ->pluck('device_token')
                ->all();

            if ($tokens === []) {
                return;
            }

            $expoPush->sendToExpoTokens(
                $tokens,
                $this->title,
                $this->body,
                $this->data,
                [
                    'sound' => 'default',
                    'badge' => null,
                    'channelId' => 'default',
                ],
            );
        } catch (Throwable) {
            // Push must never break callers
        }
    }
}
