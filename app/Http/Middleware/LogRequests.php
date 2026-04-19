<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('observability.request_logging', true)) {
            return $next($request);
        }

        $request->attributes->set('log_request_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! (bool) config('observability.request_logging', true)) {
            return;
        }

        $started = $request->attributes->get('log_request_started_at');
        $durationMs = is_float($started)
            ? (int) round((microtime(true) - $started) * 1000)
            : null;

        $user = $request->user();
        $payload = null;
        if ($request->is('api/*')) {
            $payload = $this->buildPayloadSnapshot($request);
        }

        Log::info('http.request', [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $user?->id,
            'request_id' => $request->attributes->get('request_id')
                ?? $request->headers->get(AddRequestId::HEADER),
            'input' => $payload,
            'response_status' => $response->getStatusCode(),
            'response_time_ms' => $durationMs,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPayloadSnapshot(Request $request): ?array
    {
        $max = max(1024, (int) config('observability.request_log_payload_max_bytes', 1_048_576));

        $merged = array_merge(
            $request->query->all(),
            $request->request->all(),
        );

        $sanitized = $this->sanitizeRecursive($merged);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return ['_error' => 'payload_not_json_encodable'];
        }

        if (strlen($encoded) > $max) {
            return [
                '_truncated' => true,
                '_original_bytes' => strlen($encoded),
                '_preview' => substr($encoded, 0, $max).'…',
            ];
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeRecursive(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
            'authorization',
            'credit_card',
            'card_number',
            'cvv',
        ];

        $out = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, $sensitiveKeys, true) || str_contains($lower, 'password')) {
                $out[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->sanitizeRecursive($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
