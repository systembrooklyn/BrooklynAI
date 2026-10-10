<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web frontend redirect target
    |--------------------------------------------------------------------------
    |
    | Used for the default (web) OAuth connection flow. Kept on the original
    | env var FRONTEND_CONNECTIONS_REDIRECT for backward compatibility with
    | existing deployments.
    |
    */

    'frontend_redirect' => env('FRONTEND_CONNECTIONS_REDIRECT'),

    /*
    |--------------------------------------------------------------------------
    | Mobile deeplink redirect target
    |--------------------------------------------------------------------------
    |
    | Used when the OAuth start request carries the X-Client-Platform: mobile
    | header. The value is the base URL of the mobile app's deeplink. Query
    | parameters (?status=...&error=...) are appended by the callback.
    |
    | Example: brooklynai://connections/google
    |
    */

    'frontend_redirect_mobile' => env('FRONTEND_CONNECTIONS_REDIRECT_MOBILE'),
];
