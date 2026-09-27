<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\AppendTextAction;
use App\Modules\Integrations\Application\DTOs\AppendTextInput;
use App\Modules\Integrations\Http\Requests\AppendTextRequest;
use Illuminate\Http\JsonResponse;

class AppendTextController extends Controller
{
    public function __invoke(AppendTextRequest $request, string $documentId, AppendTextAction $action): JsonResponse
    {
        try {
            $result = $action->execute(new AppendTextInput(
                userId: (int) $request->user()->id,
                documentId: $documentId,
                text: (string) $request->input('text'),
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json($result);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
