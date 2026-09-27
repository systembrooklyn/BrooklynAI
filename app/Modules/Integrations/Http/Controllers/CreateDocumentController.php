<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\CreateDocumentAction;
use App\Modules\Integrations\Application\DTOs\CreateDocumentInput;
use App\Modules\Integrations\Http\Requests\CreateDocumentRequest;
use Illuminate\Http\JsonResponse;

class CreateDocumentController extends Controller
{
    public function __invoke(CreateDocumentRequest $request, CreateDocumentAction $action): JsonResponse
    {
        try {
            $doc = $action->execute(new CreateDocumentInput(
                userId: (int) $request->user()->id,
                title: (string) $request->input('title', 'Untitled Document'),
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json($doc);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
