<?php

namespace App\Providers;

use App\Support\Observability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (Observability::telescopeShouldRegister()) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! Observability::queryLoggingEnabled()) {
            return;
        }

        DB::listen(function ($query): void {
            $requestId = request()?->headers->get(\App\Http\Middleware\AddRequestId::HEADER);

            $bindings = array_map(static function (mixed $binding): string {
                if (is_string($binding) || is_int($binding) || is_float($binding)) {
                    return (string) $binding;
                }

                return json_encode($binding, JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
            }, $query->bindings);

            $slowMs = (int) config('observability.query_slow_threshold_ms', 500);

            $payload = [
                'sql' => $query->sql,
                'bindings' => $bindings,
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
                'request_id' => $requestId,
            ];

            if ($query->time >= $slowMs) {
                Log::warning('db.query.slow', $payload);
            } else {
                Log::info('db.query', $payload);
            }
        });
    }
}
