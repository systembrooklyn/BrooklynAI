<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\ListSpreadsheetsAction;
use App\Modules\Integrations\Application\DTOs\ListSpreadsheetsInput;
use App\Modules\Integrations\Http\Requests\ListSpreadsheetsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ListSpreadsheetsController extends Controller
{
    public function __invoke(
        ListSpreadsheetsRequest $request,
        ListSpreadsheetsAction $action,
    ): JsonResponse {
        Log::info('Auth check', [
            'user' => Auth::check() ? 'Authenticated' : 'Not authenticated',
            'token' => request()->bearerToken() ?: 'No token',
        ]);

        $user = $request->user();

        try {
            $spreadsheets = $action->execute(new ListSpreadsheetsInput(
                userId: (int) $user->id,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'spreadsheets' => $spreadsheets,
                'count' => count($spreadsheets),
            ]);
        } catch (ConnectionNotFoundException $e) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to list spreadsheets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
