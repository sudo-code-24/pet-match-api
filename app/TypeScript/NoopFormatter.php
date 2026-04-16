<?php

namespace App\TypeScript;

use Spatie\TypeScriptTransformer\Formatters\Formatter;

class NoopFormatter implements Formatter
{
    public function format(array $files): void
    {
        // Intentionally no-op to avoid requiring node binaries in the PHP container.
    }
}
