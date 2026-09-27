<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\AppendSheetDataAction;
use App\Modules\Integrations\Application\DTOs\AppendSheetDataInput;
use App\Modules\Integrations\Http\Requests\AppendSheetDataRequest;
use Illuminate\Http\JsonResponse;

class AppendSheetDataController extends Controller
{
    public function __invoke(
        AppendSheetDataRequest $request,
        string $id,
        AppendSheetDataAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new AppendSheetDataInput(
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
                'message' => 'Failed to append data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
