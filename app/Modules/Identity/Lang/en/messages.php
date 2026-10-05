<?php

return [
    'too_many_attempts' => 'Too many attempts. Please try again later.',
    'login_success' => 'Login successful.',
    'invalid_credentials' => 'Invalid credentials.',

    // Password reset
    'password_reset_requested' => "If that email exists, we've sent a reset code.",
    'password_reset_success' => 'Password reset successful. Please log in with your new password.',
    'invalid_or_expired_code' => 'The reset code is invalid or has expired.',
    'too_many_requests' => 'Too many requests. Please try again later.',

    // Password reset email content
    'password_reset_email_subject' => 'Your password reset code',
    'password_reset_email_greeting' => 'Hello :name,',
    'password_reset_email_line_1' => 'You requested a password reset. Use the code below to set a new password:',
    'password_reset_email_code' => 'Reset code: :code',
    'password_reset_email_expiry' => 'This code expires in :minutes minutes.',
    'password_reset_email_line_2' => "If you didn't request this, you can safely ignore this email.",
    'password_reset_email_code_label' => 'Your reset code',
    'password_reset_email_footer' => "If you didn't request this email, no action is needed.",

    // Root API controllers (auth-adjacent endpoints outside the Identity module)
    'user_retrieved' => 'User Retrieved successfully',
    'user_registered' => 'User registered successfully',
    'user_updated' => 'User updated Successfully',
    'logout_success' => 'Successfully logged out.',
    'account_deactivated' => 'Your account has been deactivated successfully.',
    'login_failed' => 'Login failed',
];
