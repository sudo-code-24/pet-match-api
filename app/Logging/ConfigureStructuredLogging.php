<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

class ConfigureStructuredLogging
{
    public function __invoke(Logger|MonologLogger $logger): void
    {
        if (! (bool) config('observability.structured_logs', true)) {
            return;
        }

        $monolog = $logger instanceof Logger ? $logger->getLogger() : $logger;

        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter(new StructuredJsonFormatter);
        }
    }
}
