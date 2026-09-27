<?php

namespace App\Modules\Execution\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Execution',
    required: ['id', 'workflow_id', 'status', 'trigger_source', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'workflow_id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'running', 'completed', 'failed']),
        new OA\Property(property: 'trigger_source', type: 'string', enum: ['manual', 'poll', 'schedule']),
        new OA\Property(property: 'trigger_payload', type: 'object', nullable: true, additionalProperties: true),
        new OA\Property(property: 'idempotency_key', type: 'string', nullable: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finished_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'steps',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ExecutionStep'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ExecutionStep',
    required: ['id', 'position', 'step_snapshot', 'status', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'position', type: 'integer'),
        new OA\Property(property: 'step_snapshot', type: 'object', additionalProperties: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'running', 'completed', 'failed', 'skipped']),
        new OA\Property(property: 'input', type: 'object', nullable: true, additionalProperties: true),
        new OA\Property(property: 'output', type: 'object', nullable: true, additionalProperties: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finished_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class Execution
{
    #[OA\Post(
        path: '/api/workflows/{id}/execute',
        operationId: 'executions.run',
        tags: ['Executions'],
        summary: 'Manually execute a workflow',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'trigger_payload', type: 'object', nullable: true, additionalProperties: true),
                new OA\Property(
                    property: 'idempotency_key',
                    type: 'string',
                    nullable: true,
                    maxLength: 191,
                    description: 'Reserved prefixes `schedule:` and `gmail:` are rejected with 422.',
                ),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Idempotent replay', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Execution'),
                ],
            )),
            new OA\Response(response: 201, description: 'Executed', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Execution'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Workflow not executable (paused) or overlapping execution', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function run(): void {}

    #[OA\Get(
        path: '/api/workflows/{id}/executions',
        operationId: 'executions.list',
        tags: ['Executions'],
        summary: 'List executions for a workflow',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Execution')),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/api/executions/{id}',
        operationId: 'executions.show',
        tags: ['Executions'],
        summary: 'Show a single execution with steps',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Execution'),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(): void {}
}
