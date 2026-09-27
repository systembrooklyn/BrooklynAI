<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\AppendRowByHeadersAction;
use App\Modules\Integrations\Application\DTOs\AppendRowByHeadersInput;
use App\Modules\Integrations\Http\Requests\AppendRowByHeadersRequest;
use Illuminate\Http\JsonResponse;

class AppendRowByHeadersController extends Controller
{
    public function __invoke(
        AppendRowByHeadersRequest $request,
        string $spreadsheetId,
        AppendRowByHeadersAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $result = $action->execute(new AppendRowByHeadersInput(
                userId: (int) $user->id,
                spreadsheetId: $spreadsheetId,
                sheetName: (string) $request->input('sheet_name'),
                data: (array) $request->input('data'),
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
                'message' => 'Failed to append row',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
