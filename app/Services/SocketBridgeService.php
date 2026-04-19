<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocketBridgeService
{
    public function broadcast(string $event, array $payload): void
    {
        $secret = (string) config('socket.internal_secret');
        $base = (string) config('socket.server_url');
        if ($secret === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->withHeaders(['X-Internal-Secret' => $secret])
                ->acceptJson()
                ->post($base.'/internal/broadcast', [
                    'event' => $event,
                    'payload' => $payload,
                ]);
        } catch (Throwable $e) {
            if (app()->environment('local')) {
                Log::debug('socket.broadcast_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function isUserOnline(string $userId): bool
    {
        $secret = (string) config('socket.internal_secret');
        $base = (string) config('socket.server_url');
        if ($secret === '' || $base === '') {
            return false;
        }

        try {
            $res = Http::timeout(2)
                ->withHeaders(['X-Internal-Secret' => $secret])
                ->acceptJson()
                ->get($base.'/internal/presence/'.rawurlencode($userId));

            return (bool) ($res->json('online'));
        } catch (Throwable) {
            return false;
        }
    }
}
