<?php

namespace App\Modules\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'BrooklynAI API',
    description: 'API-only Laravel 12 automation platform. Modular Monolith + DDD.',
)]
#[OA\Server(url: '/', description: 'Current host')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Laravel Sanctum personal access token. Send as `Authorization: Bearer <token>`.',
)]
#[OA\Tag(name: 'Authentication', description: 'User registration and Sanctum-authenticated identity')]
#[OA\Tag(name: 'Connections', description: 'User-owned Google OAuth connections')]
#[OA\Tag(name: 'Workflows', description: 'Workflow lifecycle: CRUD, restore, activate, pause')]
#[OA\Tag(name: 'Workflow Triggers', description: 'Single trigger per workflow')]
#[OA\Tag(name: 'Workflow Steps', description: 'Positional action steps')]
#[OA\Tag(name: 'Executions', description: 'Manual execution and execution history')]
#[OA\Tag(name: 'Gmail', description: 'Gmail send + label listing')]
#[OA\Tag(name: 'Calendar', description: 'Google Calendar events')]
#[OA\Tag(name: 'Sheets', description: 'Google Sheets')]
#[OA\Tag(name: 'Docs', description: 'Google Docs')]
#[OA\Tag(name: 'Analytics', description: 'Google Analytics (GA4)')]
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Generic error response.',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    description: 'Laravel FormRequest validation failure (HTTP 422).',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CapabilityError',
    description: 'Workflow activation capability validation failure (HTTP 409).',
    required: ['message', 'error', 'context'],
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'error',
            type: 'string',
            enum: [
                'missing_connection',
                'connection_not_found_or_not_owned',
                'unsupported_capability',
                'missing_scopes',
                'unknown_integration',
                'unknown_trigger',
                'unknown_action',
            ],
        ),
        new OA\Property(property: 'context', type: 'object', additionalProperties: true),
    ],
    type: 'object',
)]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Unauthenticated.',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Resource not found or not owned by the current user.',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
)]
#[OA\Response(
    response: 'ValidationErrorResponse',
    description: 'Validation failed.',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
#[OA\Response(
    response: 'GenericError',
    description: 'Generic error.',
    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
)]
class OpenApi {}
