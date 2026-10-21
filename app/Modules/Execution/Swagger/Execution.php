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
            new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Leave the body empty to auto-test using the latest email in the connected Gmail inbox.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'trigger_payload', type: 'object', nullable: true, additionalProperties: true, description: 'Optional. If omitted, the backend auto-fetches the latest Gmail message.'),
                    new OA\Property(property: 'idempotency_key', type: 'string', nullable: true, maxLength: 191),
                ],
            )
        ),
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
            new OA\Response(response: 403, description: 'Credentials unavailable or connection not found.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'error', type: 'string', example: 'credentials_unavailable'),
                ],
            )),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Workflow not executable (paused) or overlapping execution', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 422, description: 'Validation failed, or no test email available.', content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(
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
                    ),
                    new OA\Schema(
                        properties: [
                            new OA\Property(property: 'message', type: 'string'),
                            new OA\Property(
                                property: 'error',
                                type: 'string',
                                enum: ['no_test_email_available', 'no_test_email_with_attachments_available'],
                            ),
                            new OA\Property(property: 'context', type: 'object', properties: [
                                new OA\Property(property: 'requires_attachment', type: 'boolean'),
                            ]),
                        ],
                        type: 'object',
                    ),
                ],
            )),
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
            new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
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
            new OA\Parameter(ref: '#/components/parameters/AcceptLanguage'),
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
