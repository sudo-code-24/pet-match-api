<?php

namespace App\Support;

final class Observability
{
    public static function telescopeShouldRegister(): bool
    {
        if (! class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            return false;
        }

        if (! filter_var(env('TELESCOPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return in_array((string) env('APP_ENV', 'production'), ['local', 'staging', 'production'], true);
    }

    public static function queryLoggingEnabled(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (! app()->environment('production')) {
            return true;
        }

        return (bool) config('observability.debug_queries', false);
    }
}
