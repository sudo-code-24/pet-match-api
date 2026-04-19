<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expo access token (optional)
    |--------------------------------------------------------------------------
    |
    | Higher rate limits when sending through Expo Push API. Create at
    | https://expo.dev/accounts/[account]/settings/access-tokens
    |
    */
    'access_token' => env('EXPO_ACCESS_TOKEN', ''),

];
