<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CatalogField',
    required: ['label', 'type', 'required'],
    properties: [
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['string', 'text', 'email', 'boolean', 'integer', 'select']),
        new OA\Property(property: 'required', type: 'boolean'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'default', nullable: true),
        new OA\Property(
            property: 'options',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'value', type: 'string'),
                    new OA\Property(property: 'label', type: 'string'),
                ],
            ),
        ),
        new OA\Property(
            property: 'options_source',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'operation_id', type: 'string'),
                new OA\Property(
                    property: 'params',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                ),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CatalogTrigger',
    required: ['trigger_key', 'label', 'description', 'strategy', 'config'],
    properties: [
        new OA\Property(property: 'trigger_key', type: 'string'),
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'strategy', type: 'string', enum: ['poll', 'webhook', 'provider_push', 'schedule']),
        new OA\Property(property: 'capability', type: 'string', nullable: true),
        new OA\Property(
            property: 'config',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/CatalogField'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CatalogAction',
    required: ['action_key', 'label', 'description', 'config'],
    properties: [
        new OA\Property(property: 'action_key', type: 'string'),
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'capability', type: 'string', nullable: true),
        new OA\Property(
            property: 'config',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/CatalogField'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CatalogAuth',
    required: ['type'],
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'oauth2'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CatalogIntegration',
    required: ['integration_key', 'provider_key', 'name', 'description', 'category', 'auth', 'triggers', 'actions'],
    properties: [
        new OA\Property(property: 'integration_key', type: 'string', example: 'google.gmail'),
        new OA\Property(property: 'provider_key', type: 'string', example: 'google'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'category', type: 'string'),
        new OA\Property(property: 'auth', ref: '#/components/schemas/CatalogAuth'),
        new OA\Property(property: 'triggers', type: 'array', items: new OA\Items(ref: '#/components/schemas/CatalogTrigger')),
        new OA\Property(property: 'actions', type: 'array', items: new OA\Items(ref: '#/components/schemas/CatalogAction')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CatalogResponse',
    required: ['integrations'],
    properties: [
        new OA\Property(property: 'integrations', type: 'array', items: new OA\Items(ref: '#/components/schemas/CatalogIntegration')),
    ],
    type: 'object',
)]
class Catalog
{
    #[OA\Get(
        path: '/api/catalog',
        operationId: 'catalog.show',
        tags: ['Catalog'],
        summary: 'Retrieve the automation catalog',
        description: 'Read-only, code-defined discovery of integrations, triggers, actions, capabilities, and configuration metadata. Uses the same canonical identifiers as the Workflow APIs.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/CatalogResponse'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function show(): void {}
}
