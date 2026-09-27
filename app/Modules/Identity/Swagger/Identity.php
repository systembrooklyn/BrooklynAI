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
