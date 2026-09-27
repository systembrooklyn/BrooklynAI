<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetRealtimeOverviewAction;
use App\Modules\Integrations\Application\DTOs\GetRealtimeOverviewInput;
use App\Modules\Integrations\Http\Requests\GetRealtimeOverviewRequest;
use Illuminate\Http\JsonResponse;

class GetRealtimeOverviewController extends Controller
{
    public function __invoke(GetRealtimeOverviewRequest $request, string $propertyId, GetRealtimeOverviewAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new GetRealtimeOverviewInput(
                userId: (int) $request->user()->id,
                propertyId: $propertyId,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Realtime overview retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
