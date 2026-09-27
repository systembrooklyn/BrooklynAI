<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\DeleteCalendarEventAction;
use App\Modules\Integrations\Application\DTOs\DeleteCalendarEventInput;
use App\Modules\Integrations\Http\Requests\DeleteCalendarEventRequest;
use Illuminate\Http\JsonResponse;

class DeleteCalendarEventController extends Controller
{
    public function __invoke(
        DeleteCalendarEventRequest $request,
        string $eventId,
        DeleteCalendarEventAction $action,
    ): JsonResponse {
        $user = $request->user();

        try {
            $action->execute(new DeleteCalendarEventInput(
                userId: (int) $user->id,
                eventId: $eventId,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => 'Event deleted successfully',
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => 'Connection not found',
            ], 404);
        } catch (\Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $code = $e->getCode();

            if ($code == 404) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            return response()->json([
                'message' => 'Failed to delete event',
                'error' => $error['error']['message'] ?? 'Google API error',
            ], $code);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
