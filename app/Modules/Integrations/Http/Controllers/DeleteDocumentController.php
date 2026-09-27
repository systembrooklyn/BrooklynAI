<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\DeleteDocumentAction;
use App\Modules\Integrations\Application\DTOs\DeleteDocumentInput;
use App\Modules\Integrations\Http\Requests\DeleteDocumentRequest;
use Illuminate\Http\JsonResponse;

class DeleteDocumentController extends Controller
{
    public function __invoke(DeleteDocumentRequest $request, string $documentId, DeleteDocumentAction $action): JsonResponse
    {
        try {
            $result = $action->execute(new DeleteDocumentInput(
                userId: (int) $request->user()->id,
                documentId: $documentId,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json([
                'message' => 'DOC deleted Successfully',
                'data' => $result,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
