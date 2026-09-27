<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\ClearSheetDataAction;
use App\Modules\Integrations\Application\DTOs\ClearSheetDataInput;
use App\Modules\Integrations\Http\Requests\ClearSheetDataRequest;
use Illuminate\Http\JsonResponse;

class ClearSheetDataController extends Controller
{
    public function __invoke(
        ClearSheetDataRequest $request,
        string $id,
        ClearSheetDataAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new ClearSheetDataInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                range: (string) $request->input('range'),
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
                'message' => 'Failed to clear data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
