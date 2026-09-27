<?php

namespace App\Modules\Connections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Application\Actions\DisconnectConnectionAction;
use App\Modules\Connections\Application\Actions\ListConnectionsAction;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function index(Request $request, ListConnectionsAction $action): JsonResponse
    {
        $items = $action->execute((int) $request->user()->id);

        return response()->json([
            'message' => __('connections::messages.list_success'),
            'data' => array_map(static fn ($d) => $d->toArray(), $items),
        ]);
    }

    public function destroy(
        Request $request,
        ConnectionModel $connection,
        DisconnectConnectionAction $action,
    ): JsonResponse {
        try {
            $action->execute((int) $request->user()->id, (int) $connection->id);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => __('connections::messages.not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('connections::messages.disconnect_success'),
        ]);
    }
}
