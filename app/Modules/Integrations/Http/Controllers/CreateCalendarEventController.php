<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GCalenderEventResource;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\CreateCalendarEventAction;
use App\Modules\Integrations\Application\DTOs\CreateCalendarEventInput;
use App\Modules\Integrations\Http\Requests\CreateCalendarEventRequest;
use Illuminate\Http\JsonResponse;

class CreateCalendarEventController extends Controller
{
    public function __invoke(
        CreateCalendarEventRequest $request,
        CreateCalendarEventAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $event = $action->execute(new CreateCalendarEventInput(
                userId: (int) $user->id,
                userName: (string) ($user->name ?? ''),
                userEmail: (string) $user->email,
                title: (string) $request->input('title'),
                description: $request->input('description'),
                startDateTime: (string) $request->input('start'),
                endDateTime: (string) $request->input('end'),
                attendees: (array) ($request->input('attendees') ?? []),
                sendEmailNotification: (bool) $request->input('email_notification.send', false),
                notificationSubject: $request->input('email_notification.subject'),
                notificationBody: $request->input('email_notification.body'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => 'Event created successfully',
                'data' => new GCalenderEventResource($event),
            ], 201);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create calendar event',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
