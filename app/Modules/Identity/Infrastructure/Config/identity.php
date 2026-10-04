<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset (Forgot Password)
    |--------------------------------------------------------------------------
    |
    | Controls the OTP-based password reset flow served by the Identity module.
    | All values are overridable via environment variables so operators can
    | tune them without a code change.
    |
    */

    'password_reset' => [
        // Number of digits in the numeric OTP (min 4, max 10).
        'code_length' => (int) env('PASSWORD_RESET_CODE_LENGTH', 6),

        // Minutes an issued code remains valid.
        'code_ttl_minutes' => (int) env('PASSWORD_RESET_CODE_TTL_MINUTES', 15),

        // Maximum wrong-code attempts per issued code before it is rejected.
        'max_attempts' => (int) env('PASSWORD_RESET_MAX_ATTEMPTS', 5),

        // Rate limits for POST /api/password/forgot.
        'forgot_rate_limit_per_email_per_hour' => (int) env('PASSWORD_RESET_FORGOT_RATE_EMAIL', 5),
        'forgot_rate_limit_per_ip_per_hour'    => (int) env('PASSWORD_RESET_FORGOT_RATE_IP', 20),

        // Rate limits for POST /api/password/reset.
        'reset_rate_limit_per_email_per_hour' => (int) env('PASSWORD_RESET_RESET_RATE_EMAIL', 20),
        'reset_rate_limit_per_ip_per_hour'    => (int) env('PASSWORD_RESET_RESET_RATE_IP', 60),
    ],

];
