<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GCalenderEventResource;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\ListCalendarEventsAction;
use App\Modules\Integrations\Application\DTOs\ListCalendarEventsInput;
use App\Modules\Integrations\Http\Requests\ListCalendarEventsRequest;
use Illuminate\Http\JsonResponse;

class ListCalendarEventsController extends Controller
{
    public function __invoke(
        ListCalendarEventsRequest $request,
        ListCalendarEventsAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $eventList = $action->execute(new ListCalendarEventsInput(
                userId: (int) $user->id,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => 'Event Retreived successfully',
                'data' => GCalenderEventResource::collection($eventList->getItems()),
                'count' => $eventList->count(),
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch calendar events',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
