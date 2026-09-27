<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\UpdateDocumentAction;
use App\Modules\Integrations\Application\DTOs\UpdateDocumentInput;
use App\Modules\Integrations\Http\Requests\UpdateDocumentRequest;
use Illuminate\Http\JsonResponse;

class UpdateDocumentController extends Controller
{
    public function __invoke(UpdateDocumentRequest $request, string $documentId, UpdateDocumentAction $action): JsonResponse
    {
        try {
            $result = $action->execute(new UpdateDocumentInput(
                userId: (int) $request->user()->id,
                documentId: $documentId,
                content: (string) $request->input('content'),
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'DOC Updated Successfully',
                'data' => $result,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
