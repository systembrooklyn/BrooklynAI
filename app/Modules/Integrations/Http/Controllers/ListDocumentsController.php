<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\ListDocumentsAction;
use App\Modules\Integrations\Application\DTOs\ListDocumentsInput;
use App\Modules\Integrations\Http\Requests\ListDocumentsRequest;
use Illuminate\Http\JsonResponse;

class ListDocumentsController extends Controller
{
    public function __invoke(ListDocumentsRequest $request, ListDocumentsAction $action): JsonResponse
    {
        try {
            $documents = $action->execute(new ListDocumentsInput(
                userId: (int) $request->user()->id,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'Google Docs retrieved successfully.',
                'data' => $documents,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
