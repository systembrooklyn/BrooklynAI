<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetReportAction;
use App\Modules\Integrations\Application\DTOs\GetReportInput;
use App\Modules\Integrations\Http\Requests\GetReportRequest;
use Illuminate\Http\JsonResponse;

class GetReportController extends Controller
{
    public function __invoke(GetReportRequest $request, string $propertyId, GetReportAction $action): JsonResponse
    {
        $validated = $request->validated();

        $dimensions = array_filter(explode(',', $request->input('dimensions', 'date,country,deviceCategory')));
        $metrics = array_filter(explode(',', $request->input('metrics', 'sessions,totalUsers,activeUsers,bounceRate')));
        $start = $validated['startDate'] ?? '30daysAgo';
        $end = $validated['endDate'] ?? 'today';

        try {
            $data = $action->execute(new GetReportInput(
                userId: (int) $request->user()->id,
                propertyId: $propertyId,
                dimensions: array_values($dimensions),
                metrics: array_values($metrics),
                startDate: $start,
                endDate: $end,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Report retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
