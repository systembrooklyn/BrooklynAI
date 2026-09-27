<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\UpdateSheetDataAction;
use App\Modules\Integrations\Application\DTOs\UpdateSheetDataInput;
use App\Modules\Integrations\Http\Requests\UpdateSheetDataRequest;
use Illuminate\Http\JsonResponse;

class UpdateSheetDataController extends Controller
{
    public function __invoke(
        UpdateSheetDataRequest $request,
        string $id,
        UpdateSheetDataAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new UpdateSheetDataInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                range: (string) $request->input('range'),
                values: (array) $request->input('values'),
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
                'message' => 'Failed to update data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
