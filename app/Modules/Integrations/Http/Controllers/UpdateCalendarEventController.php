<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GCalenderEventResource;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\UpdateCalendarEventAction;
use App\Modules\Integrations\Application\DTOs\UpdateCalendarEventInput;
use App\Modules\Integrations\Http\Requests\UpdateCalendarEventRequest;
use Illuminate\Http\JsonResponse;

class UpdateCalendarEventController extends Controller
{
    public function __invoke(
        UpdateCalendarEventRequest $request,
        string $eventId,
        UpdateCalendarEventAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $attendees = $request->has('attendees')
                ? (array) $request->input('attendees')
                : null;

            $event = $action->execute(new UpdateCalendarEventInput(
                userId: (int) $user->id,
                eventId: $eventId,
                title: (string) $request->input('title'),
                description: $request->input('description'),
                startDateTime: (string) $request->input('start'),
                endDateTime: (string) $request->input('end'),
                attendees: $attendees,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => 'Event updated successfully',
                'data' => new GCalenderEventResource($event),
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $status = $e->getCode();

            if ($status == 404) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            return response()->json([
                'error' => 'Failed to update event',
                'message' => $error['error']['message'] ?? 'Unknown error',
            ], $status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
