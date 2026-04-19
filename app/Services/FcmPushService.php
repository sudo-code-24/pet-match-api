<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * FCM HTTP v1 using a Google service account JSON (no extra Composer packages).
 */
class FcmPushService
{
    private ?array $cachedToken = null;

    /**
     * @param  list<string>  $deviceTokens
     */
    public function sendChatMessage(array $deviceTokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter(array_unique($deviceTokens), fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return;
        }

        $access = $this->getAccessToken();
        if ($access === null) {
            return;
        }

        $projectId = $this->projectId();
        if ($projectId === '') {
            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

        foreach ($tokens as $token) {
            try {
                Http::timeout(8)
                    ->withToken($access)
                    ->acceptJson()
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => array_map(static fn ($v): string => is_string($v) ? $v : (string) json_encode($v), $data),
                            'android' => [
                                'priority' => 'HIGH',
                            ],
                        ],
                    ]);
            } catch (Throwable $e) {
                if (app()->environment('local')) {
                    Log::debug('fcm.send_failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function projectId(): string
    {
        $fromEnv = trim((string) config('fcm.project_id'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $json = $this->loadServiceAccount();

        return $json !== null ? trim((string) ($json['project_id'] ?? '')) : '';
    }

    private function getAccessToken(): ?string
    {
        if ($this->cachedToken !== null && ($this->cachedToken['exp'] ?? 0) > time() + 60) {
            return $this->cachedToken['access_token'] ?? null;
        }

        $json = $this->loadServiceAccount();
        if ($json === null) {
            return null;
        }

        $clientEmail = (string) ($json['client_email'] ?? '');
        $privateKey = (string) ($json['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            return null;
        }

        $now = time();
        $jwt = $this->signServiceAccountJwt(
            $clientEmail,
            $privateKey,
            'https://oauth2.googleapis.com/token',
            'https://www.googleapis.com/auth/firebase.messaging',
            $now,
            $now + 3600,
        );

        try {
            $res = Http::timeout(10)->asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (Throwable) {
            return null;
        }

        $token = $res->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        $expiresIn = (int) ($res->json('expires_in') ?? 3600);
        $this->cachedToken = [
            'access_token' => $token,
            'exp' => time() + max(120, $expiresIn),
        ];

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadServiceAccount(): ?array
    {
        $path = trim((string) config('fcm.credentials_path'));
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function signServiceAccountJwt(
        string $clientEmail,
        string $privateKeyPem,
        string $audience,
        string $scope,
        int $iat,
        int $exp,
    ): string {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => $audience,
            'iat' => $iat,
            'exp' => $exp,
            'scope' => $scope,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new RuntimeException('Invalid FCM private key.');
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign FCM JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
