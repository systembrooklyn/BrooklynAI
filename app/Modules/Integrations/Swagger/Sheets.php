<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

class Sheets
{
    #[OA\Get(
        path: '/api/google-sheets',
        operationId: 'sheets.listSpreadsheets',
        tags: ['Sheets'],
        summary: 'List spreadsheets',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Sheets/Drive error'),
        ],
    )]
    public function listSpreadsheets(): void {}

    #[OA\Get(
        path: '/api/google-sheets/{id}',
        operationId: 'sheets.getSpreadsheet',
        tags: ['Sheets'],
        summary: 'Get a spreadsheet and its sheets',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function getSpreadsheet(): void {}

    #[OA\Post(
        path: '/api/google-sheets/{id}',
        operationId: 'sheets.addSheet',
        tags: ['Sheets'],
        summary: 'Add a sheet/tab',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 100),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Sheets error'),
        ],
    )]
    public function addSheet(): void {}

    #[OA\Delete(
        path: '/api/google-sheets/{id}',
        operationId: 'sheets.deleteSheet',
        tags: ['Sheets'],
        summary: 'Delete a sheet/tab',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['sheet_id'],
            properties: [
                new OA\Property(property: 'sheet_id', type: 'integer'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function deleteSheet(): void {}

    #[OA\Get(
        path: '/api/google-sheets/{id}/data',
        operationId: 'sheets.getData',
        tags: ['Sheets'],
        summary: 'Read values from a range',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'range', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function getData(): void {}

    #[OA\Put(
        path: '/api/google-sheets/{id}/data',
        operationId: 'sheets.updateData',
        tags: ['Sheets'],
        summary: 'Update values in a range',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['range', 'values'],
            properties: [
                new OA\Property(property: 'range', type: 'string'),
                new OA\Property(property: 'values', type: 'array', items: new OA\Items(type: 'array', items: new OA\Items)),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function updateData(): void {}

    #[OA\Post(
        path: '/api/google-sheets/{id}/data',
        operationId: 'sheets.appendData',
        tags: ['Sheets'],
        summary: 'Append values to a range',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['range', 'values'],
            properties: [
                new OA\Property(property: 'range', type: 'string'),
                new OA\Property(property: 'values', type: 'array', items: new OA\Items(type: 'array', items: new OA\Items)),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function appendData(): void {}

    #[OA\Delete(
        path: '/api/google-sheets/{id}/data',
        operationId: 'sheets.clearData',
        tags: ['Sheets'],
        summary: 'Clear values in a range',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['range'],
            properties: [
                new OA\Property(property: 'range', type: 'string'),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function clearData(): void {}

    #[OA\Post(
        path: '/api/google-sheets/{spreadsheetId}/append-under-header',
        operationId: 'sheets.appendRowByHeaders',
        tags: ['Sheets'],
        summary: 'Append a row mapped by header names',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'spreadsheetId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['sheet_name', 'data'],
            properties: [
                new OA\Property(property: 'sheet_name', type: 'string'),
                new OA\Property(property: 'data', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true)),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ],
    )]
    public function appendRowByHeaders(): void {}
}
