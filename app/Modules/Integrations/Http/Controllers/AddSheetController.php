<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\AddSheetAction;
use App\Modules\Integrations\Application\DTOs\AddSheetInput;
use App\Modules\Integrations\Http\Requests\AddSheetRequest;
use Illuminate\Http\JsonResponse;

class AddSheetController extends Controller
{
    public function __invoke(
        AddSheetRequest $request,
        string $id,
        AddSheetAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new AddSheetInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                title: (string) $request->input('title'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json($result);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add sheet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
