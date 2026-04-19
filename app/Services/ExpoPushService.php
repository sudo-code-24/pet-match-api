<?php

namespace App\Services;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends notifications through Expo Push API (https://exp.host/--/api/v2/push/send).
 */
class ExpoPushService
{
    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  list<string>  $expoTokens  Values like ExponentPushToken[...]
     * @param  array<string, mixed>  $data  Serialized to string values for FCM compatibility
     * @param  array{sound?: string, badge?: int|null, channelId?: string|null}  $options
     */
    public function sendToExpoTokens(
        array $expoTokens,
        string $title,
        string $body,
        array $data = [],
        array $options = [],
    ): void {
        $tokens = array_values(array_unique(array_filter($expoTokens, fn (string $t): bool => $t !== '')));
        if ($tokens === []) {
            return;
        }

        $stringData = $this->stringifyData($data);
        $sound = isset($options['sound']) ? (string) $options['sound'] : 'default';
        $channelId = isset($options['channelId']) && is_string($options['channelId']) && $options['channelId'] !== ''
            ? $options['channelId']
            : 'default';

        foreach (array_chunk($tokens, 100) as $chunk) {
            $messages = [];
            foreach ($chunk as $token) {
                $message = [
                    'to' => $token,
                    'title' => $title,
                    'body' => $body,
                    'sound' => $sound,
                    'data' => $stringData,
                    'channelId' => $channelId,
                ];
                if (array_key_exists('badge', $options) && $options['badge'] !== null) {
                    $message['badge'] = (int) $options['badge'];
                }
                $messages[] = $message;
            }

            try {
                $this->postChunk($messages);
            } catch (Throwable $e) {
                if (app()->environment('local')) {
                    Log::debug('expo.push.send_failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function postChunk(array $messages): void
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $access = trim((string) config('expo.access_token'));
        if ($access !== '') {
            $headers['Authorization'] = 'Bearer '.$access;
        }

        $response = Http::timeout(12)
            ->withHeaders($headers)
            ->post(self::SEND_URL, $messages);

        if (! $response->successful()) {
            if (app()->environment('local')) {
                Log::debug('expo.push.http_error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return;
        }

        $tickets = $payload['data'] ?? null;
        if (! is_array($tickets)) {
            return;
        }

        $invalidTokens = [];
        foreach ($tickets as $index => $ticket) {
            if (! is_array($ticket)) {
                continue;
            }
            if (($ticket['status'] ?? '') !== 'error') {
                continue;
            }
            $details = $ticket['details'] ?? null;
            $error = is_array($details) ? ($details['error'] ?? null) : null;
            if ($error !== 'DeviceNotRegistered') {
                continue;
            }
            $token = $messages[$index]['to'] ?? null;
            if (is_string($token) && $token !== '') {
                $invalidTokens[] = $token;
            }
        }

        if ($invalidTokens !== []) {
            UserDevice::query()->whereIn('device_token', $invalidTokens)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $k = (string) $key;
            if ($value === null || is_scalar($value)) {
                $out[$k] = (string) $value;

                continue;
            }
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $out[$k] = $encoded;
        }

        return $out;
    }
}
