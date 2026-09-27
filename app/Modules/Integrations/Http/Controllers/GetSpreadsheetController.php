<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetSpreadsheetAction;
use App\Modules\Integrations\Application\DTOs\GetSpreadsheetInput;
use App\Modules\Integrations\Http\Requests\GetSpreadsheetRequest;
use Illuminate\Http\JsonResponse;

class GetSpreadsheetController extends Controller
{
    public function __invoke(
        GetSpreadsheetRequest $request,
        string $id,
        GetSpreadsheetAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $spreadsheet = $action->execute(new GetSpreadsheetInput(
                userId: (int) $user->id,
                spreadsheetId: $id,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json($spreadsheet);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get spreadsheet',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
