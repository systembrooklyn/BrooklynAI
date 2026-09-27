<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GenerateFromTemplateAction;
use App\Modules\Integrations\Application\DTOs\GenerateFromTemplateInput;
use App\Modules\Integrations\Http\Requests\GenerateFromTemplateRequest;
use Illuminate\Http\JsonResponse;

class GenerateFromTemplateController extends Controller
{
    public function __invoke(GenerateFromTemplateRequest $request, GenerateFromTemplateAction $action): JsonResponse
    {
        try {
            $doc = $action->execute(new GenerateFromTemplateInput(
                userId: (int) $request->user()->id,
                name: (string) $request->input('name'),
                service: (string) $request->input('service'),
                sign: (string) $request->input('sign'),
                title: $request->input('title'),
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json($doc);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }
    }
}
