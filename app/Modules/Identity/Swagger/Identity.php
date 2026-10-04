<?php

namespace App\Modules\Identity\Swagger;

use OpenApi\Attributes as OA;

class Identity
{
    #[OA\Post(
        path: '/api/register',
        operationId: 'auth.register',
        tags: ['Authentication'],
        summary: 'Register or update a user',
        description: 'Creates a user if the email is new, otherwise updates access_expiry and has_bot_access.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'access_expiry'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'st_num', type: 'integer', nullable: true),
                    new OA\Property(property: 'access_expiry', type: 'string', format: 'date'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', additionalProperties: true),
                ],
            )),
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', additionalProperties: true),
                ],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/api/login',
        operationId: 'auth.login',
        tags: ['Authentication'],
        summary: 'Authenticate with email and password',
        description: 'Verifies credentials and returns a Sanctum bearer token. Returns the same 401 body for every credential failure (unknown email, wrong password, soft-deleted user, or user without a usable password).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Login successful.'),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'user', type: 'object', additionalProperties: true),
                    ]),
                ],
            )),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.'),
                ],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/api/password/forgot',
        operationId: 'auth.password.forgot',
        tags: ['Authentication'],
        summary: 'Request a password reset code',
        description: 'Sends a 6-digit OTP to the given email address if it belongs to an existing user. The response is identical for known and unknown emails to prevent email enumeration. The code expires in 15 minutes. Rate limited to 5 requests per email per hour and 20 requests per IP per hour.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reset code dispatched (or email does not exist — same response)', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: "If that email exists, we've sent a reset code."),
                ],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 429, description: 'Too many requests', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Too many requests. Please try again later.'),
                ],
            )),
        ],
    )]
    public function forgotPassword(): void {}

    #[OA\Post(
        path: '/api/password/reset',
        operationId: 'auth.password.reset',
        tags: ['Authentication'],
        summary: 'Reset password with OTP code',
        description: 'Verifies the 6-digit OTP and sets the new password. On success, all existing Sanctum tokens for the user are revoked. Returns a single generic 422 for every failure mode (missing code, expired code, used code, wrong code, or attempts exhausted). Rate limited to 20 requests per email per hour and 60 requests per IP per hour.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'code', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'code', type: 'string', minLength: 4, maxLength: 10, example: '482913'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, maxLength: 255),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successfully', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Password reset successful. Please log in with your new password.'),
                ],
            )),
            new OA\Response(response: 422, description: 'Invalid, expired, or exhausted reset code', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'The reset code is invalid or has expired.'),
                ],
            )),
            new OA\Response(response: 429, description: 'Too many requests', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Too many requests. Please try again later.'),
                ],
            )),
        ],
    )]
    public function resetPassword(): void {}

    #[OA\Get(
        path: '/api/user',
        operationId: 'auth.user',
        tags: ['Authentication'],
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', additionalProperties: true),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function user(): void {}

    #[OA\Get(
        path: '/api/auth/google/redirect',
        operationId: 'auth.google.redirect',
        tags: ['Authentication'],
        summary: 'Begin Google login',
        responses: [
            new OA\Response(response: 302, description: 'Redirect to Google OAuth', headers: [
                new OA\Header(header: 'Location', description: 'Google OAuth consent URL', schema: new OA\Schema(type: 'string')),
            ]),
        ],
    )]
    public function googleRedirect(): void {}

    #[OA\Get(
        path: '/api/auth/google/redirect-google',
        operationId: 'auth.google.redirectGoogle',
        tags: ['Authentication'],
        summary: 'Begin Google login with the full legacy scope set',
        responses: [
            new OA\Response(response: 302, description: 'Redirect to Google OAuth', headers: [
                new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string')),
            ]),
        ],
    )]
    public function googleRedirectGoogle(): void {}

    #[OA\Get(
        path: '/api/auth/google/callback',
        operationId: 'auth.google.callback',
        tags: ['Authentication'],
        summary: 'Google login callback',
        responses: [
            new OA\Response(response: 302, description: 'Redirect to frontend with token', headers: [
                new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string')),
            ]),
            new OA\Response(response: 500, description: 'Login failed', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'error', type: 'string')],
            )),
        ],
    )]
    public function googleCallback(): void {}

    #[OA\Post(
        path: '/api/logout',
        operationId: 'auth.logout',
        tags: ['Authentication'],
        summary: 'Revoke all Sanctum tokens for the authenticated user',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function logout(): void {}

    #[OA\Post(
        path: '/api/account/deactivate',
        operationId: 'auth.deactivate',
        tags: ['Authentication'],
        summary: 'Soft-delete the authenticated user and revoke tokens',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [new OA\Property(property: 'reason', type: 'string', nullable: true)],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function deactivate(): void {}
}
