<?php

namespace App\Modules\Automation\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Workflow',
    required: ['id', 'name', 'status', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'paused']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'trigger', ref: '#/components/schemas/WorkflowTrigger', nullable: true),
        new OA\Property(
            property: 'steps',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/WorkflowStep'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'WorkflowTrigger',
    required: ['id', 'integration_key', 'trigger_key', 'strategy', 'config'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'integration_key', type: 'string', example: 'google.gmail'),
        new OA\Property(property: 'trigger_key', type: 'string', example: 'new_email_received'),
        new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
        new OA\Property(property: 'strategy', type: 'string', enum: ['poll', 'webhook', 'provider_push', 'schedule']),
        new OA\Property(property: 'config', type: 'object', additionalProperties: true),
        new OA\Property(property: 'interval_minutes', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'WorkflowStep',
    required: ['id', 'position', 'integration_key', 'action_key', 'config'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'position', type: 'integer', minimum: 1),
        new OA\Property(property: 'integration_key', type: 'string', example: 'google.gmail'),
        new OA\Property(property: 'action_key', type: 'string', example: 'send_email'),
        new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
        new OA\Property(property: 'config', type: 'object', additionalProperties: true),
    ],
    type: 'object',
)]
class Automation
{
    #[OA\Get(
        path: '/api/workflows',
        operationId: 'workflows.list',
        tags: ['Workflows'],
        summary: 'List workflows',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'trashed', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Workflow')),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/workflows',
        operationId: 'workflows.create',
        tags: ['Workflows'],
        summary: 'Create a workflow in draft state',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 120),
                new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 5000),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/workflows/{id}',
        operationId: 'workflows.show',
        tags: ['Workflows'],
        summary: 'Show a workflow including trigger and steps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(): void {}

    #[OA\Put(
        path: '/api/workflows/{id}',
        operationId: 'workflows.update',
        tags: ['Workflows'],
        summary: 'Update workflow name and description',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 120),
                new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 5000),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/workflows/{id}',
        operationId: 'workflows.delete',
        tags: ['Workflows'],
        summary: 'Soft-delete a workflow',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
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

    #[OA\Post(
        path: '/api/workflows/{id}/restore',
        operationId: 'workflows.restore',
        tags: ['Workflows'],
        summary: 'Restore a soft-deleted workflow (does not auto-activate)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function restore(): void {}

    #[OA\Post(
        path: '/api/workflows/{id}/activate',
        operationId: 'workflows.activate',
        tags: ['Workflows'],
        summary: 'Activate a workflow (draft → active) after capability validation',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Cannot activate (missing trigger, invalid transition, or capability failure)', content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(ref: '#/components/schemas/ErrorResponse'),
                    new OA\Schema(ref: '#/components/schemas/CapabilityError'),
                ],
            )),
        ],
    )]
    public function activate(): void {}

    #[OA\Post(
        path: '/api/workflows/{id}/pause',
        operationId: 'workflows.pause',
        tags: ['Workflows'],
        summary: 'Pause an active workflow',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paused', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Workflow'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/GenericError'),
        ],
    )]
    public function pause(): void {}

    #[OA\Put(
        path: '/api/workflows/{id}/trigger',
        operationId: 'workflowTriggers.upsert',
        tags: ['Workflow Triggers'],
        summary: 'Create or replace the workflow trigger',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['integration_key', 'trigger_key'],
            properties: [
                new OA\Property(property: 'integration_key', type: 'string', example: 'google.gmail'),
                new OA\Property(property: 'trigger_key', type: 'string', example: 'new_email_received'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
                new OA\Property(property: 'interval_minutes', type: 'integer', nullable: true, minimum: 1, maximum: 1440),
                new OA\Property(property: 'config', type: 'object', nullable: true, additionalProperties: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Upserted', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/WorkflowTrigger'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function upsertTrigger(): void {}

    #[OA\Delete(
        path: '/api/workflows/{id}/trigger',
        operationId: 'workflowTriggers.delete',
        tags: ['Workflow Triggers'],
        summary: 'Delete the workflow trigger',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function deleteTrigger(): void {}

    #[OA\Post(
        path: '/api/workflows/{id}/steps',
        operationId: 'workflowSteps.store',
        tags: ['Workflow Steps'],
        summary: 'Append a workflow step',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['integration_key', 'action_key'],
            properties: [
                new OA\Property(property: 'integration_key', type: 'string', example: 'google.gmail'),
                new OA\Property(property: 'action_key', type: 'string', example: 'send_email'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
                new OA\Property(property: 'config', type: 'object', nullable: true, additionalProperties: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/WorkflowStep'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function storeStep(): void {}

    #[OA\Put(
        path: '/api/workflows/{id}/steps/{position}',
        operationId: 'workflowSteps.update',
        tags: ['Workflow Steps'],
        summary: 'Replace a workflow step at a position',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'position', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['integration_key', 'action_key'],
            properties: [
                new OA\Property(property: 'integration_key', type: 'string'),
                new OA\Property(property: 'action_key', type: 'string'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
                new OA\Property(property: 'config', type: 'object', nullable: true, additionalProperties: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/WorkflowStep'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function updateStep(): void {}

    #[OA\Delete(
        path: '/api/workflows/{id}/steps/{position}',
        operationId: 'workflowSteps.delete',
        tags: ['Workflow Steps'],
        summary: 'Delete a workflow step at a position (positions are not reindexed)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'position', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function deleteStep(): void {}
}
