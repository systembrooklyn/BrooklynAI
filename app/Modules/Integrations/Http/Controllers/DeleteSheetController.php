<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\DeleteSheetAction;
use App\Modules\Integrations\Application\DTOs\DeleteSheetInput;
use App\Modules\Integrations\Http\Requests\DeleteSheetRequest;
use Illuminate\Http\JsonResponse;

class DeleteSheetController extends Controller
{
    public function __invoke(
        DeleteSheetRequest $request,
        string $id,
        DeleteSheetAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new DeleteSheetInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                sheetId: (int) $request->input('sheet_id'),
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
                'message' => 'Failed to delete sheet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
