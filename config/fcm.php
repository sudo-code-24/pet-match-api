<?php

return [
    /**
     * Absolute path to the Firebase / Google Cloud service account JSON file (FCM HTTP v1).
     * Leave empty to disable push sends (no error in chat flow).
     */
    'credentials_path' => env('FCM_SERVICE_ACCOUNT_PATH', ''),

    /**
     * Overrides project_id from the JSON when set.
     */
    'project_id' => env('FCM_PROJECT_ID', ''),
];
