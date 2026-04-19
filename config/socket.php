<?php

return [
    /**
     * Base URL of the Node Socket.IO process (used for internal HTTP: presence + broadcast).
     */
    'server_url' => rtrim((string) env('SOCKET_SERVER_URL', 'http://127.0.0.1:6001'), '/'),

    /**
     * Shared secret for POST /internal/broadcast and GET /internal/presence/* on the socket server.
     */
    'internal_secret' => (string) env('SOCKET_INTERNAL_SECRET', ''),
];
