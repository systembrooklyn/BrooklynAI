<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\ListPropertiesAction;
use App\Modules\Integrations\Application\DTOs\ListPropertiesInput;
use App\Modules\Integrations\Http\Requests\ListPropertiesRequest;
use Illuminate\Http\JsonResponse;

class ListPropertiesController extends Controller
{
    public function __invoke(ListPropertiesRequest $request, ListPropertiesAction $action): JsonResponse
    {
        try {
            $properties = $action->execute(new ListPropertiesInput(
                userId: (int) $request->user()->id,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Properties retrieved successfully.',
                'data' => $properties,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
