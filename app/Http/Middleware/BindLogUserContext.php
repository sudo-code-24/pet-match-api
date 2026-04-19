<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BindLogUserContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            Log::withContext(['user_id' => $user->id]);
        }

        return $next($request);
    }
}
