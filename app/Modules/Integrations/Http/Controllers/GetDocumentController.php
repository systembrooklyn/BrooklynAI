<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetDocumentAction;
use App\Modules\Integrations\Application\DTOs\GetDocumentInput;
use App\Modules\Integrations\Http\Requests\GetDocumentRequest;
use Illuminate\Http\JsonResponse;

class GetDocumentController extends Controller
{
    public function __invoke(GetDocumentRequest $request, string $documentId, GetDocumentAction $action): JsonResponse
    {
        try {
            $doc = $action->execute(new GetDocumentInput(
                userId: (int) $request->user()->id,
                documentId: $documentId,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json($doc);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
