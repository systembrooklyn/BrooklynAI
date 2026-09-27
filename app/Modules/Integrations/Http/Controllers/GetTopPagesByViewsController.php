<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetTopPagesByViewsAction;
use App\Modules\Integrations\Application\DTOs\GetTopPagesByViewsInput;
use App\Modules\Integrations\Http\Requests\GetTopPagesByViewsRequest;
use Illuminate\Http\JsonResponse;

class GetTopPagesByViewsController extends Controller
{
    public function __invoke(GetTopPagesByViewsRequest $request, string $propertyId, GetTopPagesByViewsAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new GetTopPagesByViewsInput(
                userId: (int) $request->user()->id,
                propertyId: $propertyId,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Views by page title retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
