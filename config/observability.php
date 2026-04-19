<?php

return [

    'request_logging' => filter_var(env('REQUEST_LOGGING', true), FILTER_VALIDATE_BOOLEAN),

    'debug_queries' => filter_var(env('DEBUG_QUERIES', false), FILTER_VALIDATE_BOOLEAN),

    'query_slow_threshold_ms' => (int) env('QUERY_SLOW_THRESHOLD_MS', 500),

    'request_log_payload_max_bytes' => (int) env('REQUEST_LOG_PAYLOAD_MAX_BYTES', 1_048_576),

    'structured_logs' => filter_var(env('STRUCTURED_LOGS', true), FILTER_VALIDATE_BOOLEAN),

];
