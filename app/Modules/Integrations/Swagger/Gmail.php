<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GmailLabel',
    required: ['id', 'name', 'type'],
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
    ],
    type: 'object',
)]
class Gmail
{
    #[OA\Post(
        path: '/api/email/send',
        operationId: 'gmail.send',
        tags: ['Gmail'],
        summary: 'Send an email via Gmail',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['to', 'subject', 'body'],
            properties: [
                new OA\Property(property: 'to', type: 'string', format: 'email'),
                new OA\Property(property: 'subject', type: 'string', maxLength: 255),
                new OA\Property(property: 'body', type: 'string'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Sent', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Gmail send failed', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'error', type: 'string'),
                    new OA\Property(property: 'details', type: 'string', nullable: true),
                ],
            )),
        ],
    )]
    public function send(): void {}

    #[OA\Get(
        path: '/api/gmail/labels',
        operationId: 'gmail.labels',
        tags: ['Gmail'],
        summary: 'List Gmail labels (read-only)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/GmailLabel')),
                ],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Credentials unavailable or Gmail error'),
        ],
    )]
    public function labels(): void {}
}
