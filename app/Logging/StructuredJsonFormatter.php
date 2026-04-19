<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class StructuredJsonFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $ctx = $record->context;
        $requestId = $ctx['request_id'] ?? null;
        $userId = $ctx['user_id'] ?? null;
        unset($ctx['request_id'], $ctx['user_id']);

        $payload = [
            'timestamp' => $record->datetime->format(\DateTimeInterface::ATOM),
            'level' => strtolower($record->level->getName()),
            'request_id' => $requestId,
            'user_id' => $userId,
            'message' => $record->message,
            'context' => array_merge($record->extra, $ctx),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
    }

    public function formatBatch(array $records): string
    {
        $lines = '';
        foreach ($records as $record) {
            $lines .= $this->format($record);
        }

        return $lines;
    }
}
