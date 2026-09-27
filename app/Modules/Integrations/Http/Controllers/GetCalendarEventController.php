<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GCalenderEventResource;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\GetCalendarEventAction;
use App\Modules\Integrations\Application\DTOs\GetCalendarEventInput;
use App\Modules\Integrations\Http\Requests\GetCalendarEventRequest;
use Illuminate\Http\JsonResponse;

class GetCalendarEventController extends Controller
{
    public function __invoke(
        GetCalendarEventRequest $request,
        string $eventId,
        GetCalendarEventAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $event = $action->execute(new GetCalendarEventInput(
                userId: (int) $user->id,
                eventId: $eventId,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => 'Event Retreived successfully',
                'data' => new GCalenderEventResource($event),
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Google\Service\Exception $e) {
            $error = $e->getMessage();
            $code = $e->getCode();

            if ($code == 404) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            return response()->json([
                'message' => 'Failed to fetch event',
                'error' => $error,
            ], $code);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
