<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $id = is_string($incoming) && $incoming !== '' && Str::isUuid($incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        $request->attributes->set('request_id', $id);

        Log::withContext([
            'request_id' => $id,
        ]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
