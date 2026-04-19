<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry): bool {
            if (! app()->environment('production')) {
                return true;
            }

            return $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if (app()->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);

        Telescope::hideRequestHeaders([
            'cookie',
            'authorization',
            'x-api-key',
            'php-auth-pw',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user): bool {
            if (! $user) {
                return false;
            }

            if (($user->role ?? '') === 'admin') {
                return true;
            }

            $raw = (string) env('TELESCOPE_ALLOWED_EMAILS', '');
            $allowed = array_values(array_filter(array_map(
                static fn (string $e): string => strtolower(trim($e)),
                explode(',', $raw),
            )));

            if ($allowed === []) {
                return false;
            }

            return in_array(strtolower((string) $user->email), $allowed, true);
        });
    }
}
