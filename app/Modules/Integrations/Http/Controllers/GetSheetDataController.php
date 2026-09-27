<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetSheetDataAction;
use App\Modules\Integrations\Application\DTOs\GetSheetDataInput;
use App\Modules\Integrations\Http\Requests\GetSheetDataRequest;
use Illuminate\Http\JsonResponse;

class GetSheetDataController extends Controller
{
    public function __invoke(
        GetSheetDataRequest $request,
        string $id,
        GetSheetDataAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $data = $action->execute(new GetSheetDataInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                range: (string) $request->input('range'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'range' => $request->input('range'),
                'values' => $data,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to read data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
