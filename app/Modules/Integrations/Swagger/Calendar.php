<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

class Calendar
{
    #[OA\Post(
        path: '/api/calendar/events',
        operationId: 'calendar.create',
        tags: ['Calendar'],
        summary: 'Create a calendar event',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'start', 'end'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'start', type: 'string', example: '2026-09-20 10:00:00'),
                new OA\Property(property: 'end', type: 'string', example: '2026-09-20 11:00:00'),
                new OA\Property(property: 'attendees', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'email')),
                new OA\Property(property: 'email_notification', type: 'object', nullable: true, properties: [
                    new OA\Property(property: 'send', type: 'boolean'),
                    new OA\Property(property: 'subject', type: 'string', nullable: true),
                    new OA\Property(property: 'body', type: 'string', nullable: true),
                ]),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Google Calendar error'),
        ],
    )]
    public function create(): void {}

    #[OA\Get(
        path: '/api/calendar/events',
        operationId: 'calendar.list',
        tags: ['Calendar'],
        summary: 'List calendar events',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Google Calendar error'),
        ],
    )]
    public function list(): void {}

    #[OA\Get(
        path: '/api/calendar/events/{eventId}',
        operationId: 'calendar.show',
        tags: ['Calendar'],
        summary: 'Get a calendar event',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Google Calendar error'),
        ],
    )]
    public function show(): void {}

    #[OA\Put(
        path: '/api/calendar/events/{eventId}',
        operationId: 'calendar.update',
        tags: ['Calendar'],
        summary: 'Update a calendar event',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'start', 'end'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'start', type: 'string'),
                new OA\Property(property: 'end', type: 'string'),
                new OA\Property(property: 'attendees', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'email')),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Google Calendar error'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/calendar/events/{eventId}',
        operationId: 'calendar.delete',
        tags: ['Calendar'],
        summary: 'Delete a calendar event',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Google Calendar error'),
        ],
    )]
    public function delete(): void {}
}
