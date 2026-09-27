<?php

namespace App\Modules\Integrations\Swagger;

use OpenApi\Attributes as OA;

class Analytics
{
    #[OA\Get(
        path: '/api/google/analytics/properties',
        operationId: 'analytics.listProperties',
        tags: ['Analytics'],
        summary: 'List GA4 properties',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Analytics error'),
        ],
    )]
    public function listProperties(): void {}

    #[OA\Post(
        path: '/api/google/analytics/properties/{propertyId}',
        operationId: 'analytics.report',
        tags: ['Analytics'],
        summary: 'Run a GA4 report',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'propertyId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'startDate', type: 'string', nullable: true),
                new OA\Property(property: 'endDate', type: 'string', nullable: true),
                new OA\Property(property: 'dimensions', type: 'string', nullable: true),
                new OA\Property(property: 'metrics', type: 'string', nullable: true),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Analytics error'),
        ],
    )]
    public function report(): void {}

    #[OA\Get(
        path: '/api/google/analytics/properties/{propertyId}/realtime',
        operationId: 'analytics.realtime',
        tags: ['Analytics'],
        summary: 'GA4 realtime overview',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'propertyId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Analytics error'),
        ],
    )]
    public function realtime(): void {}

    #[OA\Post(
        path: '/api/google/analytics/home-metrics/{propertyId}',
        operationId: 'analytics.homeMetrics',
        tags: ['Analytics'],
        summary: 'GA4 home-screen metrics',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'propertyId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'startDate', type: 'string', nullable: true),
                new OA\Property(property: 'endDate', type: 'string', nullable: true),
                new OA\Property(property: 'connection_id', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 500, description: 'Analytics error'),
        ],
    )]
    public function homeMetrics(): void {}

    #[OA\Get(
        path: '/api/google/analytics/viewsbypage/{propertyId}',
        operationId: 'analytics.topPagesByViews',
        tags: ['Analytics'],
        summary: 'GA4 top pages by views',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'propertyId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'connection_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, description: 'Analytics error'),
        ],
    )]
    public function topPagesByViews(): void {}
}
