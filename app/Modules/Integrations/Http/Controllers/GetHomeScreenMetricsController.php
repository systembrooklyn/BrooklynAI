<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetHomeScreenMetricsAction;
use App\Modules\Integrations\Application\DTOs\GetHomeScreenMetricsInput;
use App\Modules\Integrations\Http\Requests\GetHomeScreenMetricsRequest;
use Illuminate\Http\JsonResponse;

class GetHomeScreenMetricsController extends Controller
{
    public function __invoke(GetHomeScreenMetricsRequest $request, string $propertyId, GetHomeScreenMetricsAction $action): JsonResponse
    {
        $validated = $request->validated();

        $start = $validated['startDate'] ?? 'today';
        $end = $validated['endDate'] ?? 'today';

        try {
            $data = $action->execute(new GetHomeScreenMetricsInput(
                userId: (int) $request->user()->id,
                propertyId: $propertyId,
                startDate: $start,
                endDate: $end,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Home screen metrics retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
