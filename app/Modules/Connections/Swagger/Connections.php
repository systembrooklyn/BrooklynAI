<?php

namespace App\Modules\Connections\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Connection',
    required: ['id', 'provider', 'external_account_id', 'scopes', 'status', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'provider', type: 'string', example: 'google'),
        new OA\Property(property: 'external_account_id', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'display_name', type: 'string', nullable: true),
        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'error', 'needs_reauth', 'revoked']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class Connections
{
    #[OA\Get(
        path: '/api/connections',
        operationId: 'connections.list',
        tags: ['Connections'],
        summary: 'List user-owned connections',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Connection')),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/connections/google/start',
        operationId: 'connections.startGoogle',
        tags: ['Connections'],
        summary: 'Begin Google OAuth for a capability',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'capability', type: 'string', nullable: true, example: 'gmail'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'redirect_url', type: 'string', format: 'uri')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function start(): void {}

    #[OA\Delete(
        path: '/api/connections/{connection}',
        operationId: 'connections.disconnect',
        tags: ['Connections'],
        summary: 'Disconnect a user-owned connection',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(): void {}

    #[OA\Get(
        path: '/api/connections/google/callback',
        operationId: 'connections.googleCallback',
        tags: ['Connections'],
        summary: 'Google OAuth callback (redirects to frontend)',
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to configured frontend with status or error query param'),
            new OA\Response(response: 500, description: 'Server misconfigured'),
        ],
    )]
    public function callback(): void {}
}
