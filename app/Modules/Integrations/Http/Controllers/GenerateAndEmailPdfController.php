<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GenerateAndEmailPdfAction;
use App\Modules\Integrations\Application\DTOs\GenerateAndEmailPdfInput;
use App\Modules\Integrations\Http\Requests\GenerateAndEmailPdfRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GenerateAndEmailPdfController extends Controller
{
    public function __invoke(GenerateAndEmailPdfRequest $request, GenerateAndEmailPdfAction $action): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $action->execute(new GenerateAndEmailPdfInput(
                userId: (int) $user->id,
                userEmail: (string) $user->email,
                toEmails: (array) $request->input('to'),
                subject: (string) $request->input('subject'),
                body: $request->input('body', ''),
                data: (array) $request->input('data'),
                filename: $request->input('filename', 'document.pdf'),
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));

            return response()->json($result);
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        } catch (\Exception $e) {
            Log::error('Generate & Email PDF Error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate and send document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
