<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

class Docs
{
    #[OA\Get(
        path: '/api/google/docs',
        operationId: 'docs.list',
        tags: ['Docs'],
        summary: 'List Google Docs',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function list(): void {}

    #[OA\Post(
        path: '/api/google/docs',
        operationId: 'docs.create',
        tags: ['Docs'],
        summary: 'Create a Google Doc',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', nullable: true, maxLength: 255),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function create(): void {}

    #[OA\Post(
        path: '/api/google/docs/generate',
        operationId: 'docs.generate',
        tags: ['Docs'],
        summary: 'Create a document from a template',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'service', 'sign'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'service', type: 'string'),
                new OA\Property(property: 'sign', type: 'string'),
                new OA\Property(property: 'title', type: 'string', nullable: true),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function generate(): void {}

    #[OA\Post(
        path: '/api/google/docs/generate-and-email',
        operationId: 'docs.generateAndEmail',
        tags: ['Docs'],
        summary: 'Generate a PDF from a template and email it via Gmail',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['to', 'subject', 'data'],
            properties: [
                new OA\Property(property: 'to', type: 'array', items: new OA\Items(type: 'string', format: 'email')),
                new OA\Property(property: 'subject', type: 'string'),
                new OA\Property(property: 'body', type: 'string', nullable: true),
                new OA\Property(property: 'data', type: 'object', additionalProperties: true),
                new OA\Property(property: 'filename', type: 'string', nullable: true, maxLength: 255),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Docs or Gmail error'),
        ],
    )]
    public function generateAndEmail(): void {}

    #[OA\Get(
        path: '/api/google/docs/{documentId}/pdf',
        operationId: 'docs.downloadPdf',
        tags: ['Docs'],
        summary: 'Export a Google Doc as PDF',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF binary', content: new OA\MediaType(
                mediaType: 'application/pdf',
                schema: new OA\Schema(type: 'string', format: 'binary'),
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function downloadPdf(): void {}

    #[OA\Get(
        path: '/api/google/docs/{documentId}',
        operationId: 'docs.show',
        tags: ['Docs'],
        summary: 'Get a Google Doc',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/api/google/docs/{documentId}',
        operationId: 'docs.appendText',
        tags: ['Docs'],
        summary: 'Append text to a Google Doc',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['text'],
            properties: [
                new OA\Property(property: 'text', type: 'string'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function appendText(): void {}

    #[OA\Put(
        path: '/api/google/docs/{documentId}',
        operationId: 'docs.update',
        tags: ['Docs'],
        summary: 'Replace the content of a Google Doc',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['content'],
            properties: [
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/google/docs/{documentId}',
        operationId: 'docs.delete',
        tags: ['Docs'],
        summary: 'Move a Google Doc to trash',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Docs error'),
        ],
    )]
    public function delete(): void {}
}
