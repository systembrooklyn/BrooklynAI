<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GCalenderEventResource;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\GmailService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{


    public function createEvent(Request $request)
    {
        // Get the user to operate on (default to current user)
        // $targetUserEmail = $request->input('user_email', $request->user()->email);
        // $targetUser = User::where('email', $targetUserEmail)->first();



        // Get the currently authenticated user (via Bearer token)
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                // 'message' => "User with email {$targetUserEmail} not found."
                'message' => "Unauthenticated. Please log in."
            ], 401);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date_format:Y-m-d H:i:s',
            'end' => 'required|date_format:Y-m-d H:i:s|after:start',
            'attendees' => 'nullable|array',
            'attendees.*' => 'email', // Validate each attendee is an email
            'email_notification.send' => 'boolean',
            'email_notification.subject' => 'nullable|string',
            'email_notification.body' => 'nullable|string',

            // 'user_email' => 'nullable|exists:users,email' // Optional parameter
        ]);

        try {
            // Convert date formats if needed (replace space with T)
            $startDateTime = str_replace(' ', 'T', $request->start);
            $endDateTime = str_replace(' ', 'T', $request->end);

            $calendar = new GoogleCalendarService($user);
            $event = $calendar->createEvent(
                $request->title,
                $request->description,
                $startDateTime,
                $endDateTime,
                $request->attendees ?? [],
            );
            if ($request->input('email_notification.send')) {
                $subject = $request->input('email_notification.subject', 'You’re invited to an event');
                $body = $request->input('email_notification.body', "You've been invited to '{$request->title}'.");
                $attendees = $request->input('attendees', []);

                try {
                    $calendar->sendEmailToAttendees($attendees, $subject, $body);
                } catch (\Exception $e) {
                    // Optional: log failure but don't block event creation
                    \Illuminate\Support\Facades\Log::warning("Failed to send email: " . $e->getMessage());
                }
            }

            return response()->json(
                [
                    'message' => 'Event created successfully',
                    'data' => new GCalenderEventResource($event)
                ],
                201
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create calendar event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List events from a specific user's calendar
     * 
     * For testing: Include 'user_id' in the request to target a different user
     */
    public function listEvents(Request $request)
    {
        // $targetUserEmail = $request->input('user_email')
        //     ?? $request->user()->email;

        // $targetUser = User::where('email', $targetUserEmail)->first();
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                // 'message' => "User not found."], 404);
                'message' => "Unauthentucated. Please log in."
            ], 401);
        }

        try {
            $calendar = new GoogleCalendarService($user);
            $eventList = $calendar->listEvents(10); // Get up to 10 upcoming events
            // $cleanEvents = collect($eventList->getItems())->map(function ($event) {
            //     return [
            //         'id' => $event->getId(),
            //         'summary' => $event->getSummary() ?? '(No title)',
            //         'description' => $event->getDescription(),
            //         'location' => $event->getLocation(),

            //         'start' => [
            //             'dateTime' => $event->getStart()->getDateTime(),
            //             'timeZone' => $event->getStart()->getTimeZone(),
            //         ],
            //         'end' => [
            //             'dateTime' => $event->getEnd()->getDateTime(),
            //             'timeZone' => $event->getEnd()->getTimeZone(),
            //         ],

            //         'attendees' => collect($event->getAttendees() ?? [])
            //             ->map(fn($attendee) => ['email' => $attendee->getEmail()])
            //             ->values()
            //             ->toArray(),

            //         'status' => $event->getStatus(),
            //         'created' => $event->getCreated(),
            //         'updated' => $event->getUpdated(),
            //     ];
            // })->values(); // Re-index after mapping
            // ->filter(fn($e) => !empty($e['summary']))->values(); // Optional filter

            return response()->json([
                'message' => 'Event Retreived successfully',
                'data' => GCalenderEventResource::collection($eventList->getItems()),
                'count' => $eventList->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch calendar events',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function getEvent($eventId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            // Initialize Google service for this user
            $calendarService = new GoogleCalendarService($user);
            $service = $calendarService->getService();;

            // Fetch the event from Google Calendar
            $event = $service->events->get('primary', $eventId);

            return response()->json(
                [
                    'message' => 'Event Retreived successfully',
                    'data' => new GCalenderEventResource($event),

                    // 'status' => $event->getStatus(), // confirmed, cancelled, tentative
                    // 'created' => $event->getCreated(),
                    // 'updated' => $event->getUpdated(),
                    // 'googleCalendarLink' => $event->getHtmlLink(),
                ]
            );
        } catch (\Google\Service\Exception $e) {
            // Google API error (e.g., event not found)
            $error = $e->getMessage();
            $code = $e->getCode();

            if ($code == 404) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            return response()->json([
                'message' => 'Failed to fetch event',
                'error' => $error
            ], $code);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update an existing calendar event
     */
    public function updateEvent(Request $request, $eventId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Validate input
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date_format:Y-m-d H:i:s',
            'end' => 'required|date_format:Y-m-d H:i:s|after:start',
            'attendees' => 'nullable|array',
            'attendees.*' => 'email'
        ]);

        try {

            // ✅ Step 1: Format dates to ISO8601 (replace space with T)
            $isoStart = str_replace(' ', 'T', $request->start);
            $isoEnd   = str_replace(' ', 'T', $request->end);
            $timeZone = config('app.timezone', 'Africa/Cairo');
            // Initialize Google Calendar service
            $calendar = new GoogleCalendarService($user);
            $service = $calendar->getService();

            // Fetch the existing event
            $event = $service->events->get('primary', $eventId);

            // Update basic fields
            $event->setSummary($request->title);
            $event->setDescription($request->description);

            // Update start time
            $startObj = new \Google\Service\Calendar\EventDateTime();
            $startObj->setDateTime($isoStart);           // ← Use formatted ISO string
            $startObj->setTimeZone($timeZone);       // ← Set IANA timezone
            $event->setStart($startObj);


            // Update end time
            $endObj = new \Google\Service\Calendar\EventDateTime();
            $endObj->setDateTime($isoEnd);
            $endObj->setTimeZone($timeZone);
            $event->setEnd($endObj);

            // Update attendees
            if ($request->has('attendees')) {
                $attendeeObjects = [];
                foreach ($request->attendees as $email) {
                    $attendee = new \Google\Service\Calendar\EventAttendee();
                    $attendee->setEmail(is_string($email) ? $email : $email['email']);
                    $attendeeObjects[] = $attendee;
                }
                $event->setAttendees($attendeeObjects);
            }

            // Save updated event
            $updatedEvent = $service->events->update('primary', $eventId, $event);

            // Return clean response
            return response()->json([
                'message' => 'Event updated successfully',
                'data' => new GCalenderEventResource($updatedEvent)
            ]);
        } catch (\Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $status = $e->getCode();

            if ($status == 404) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            return response()->json([
                'error' => 'Failed to update event',
                'message' => $error['error']['message'] ?? 'Unknown error'
            ], $status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Delete an event from the user's Google Calendar
     */
    public function deleteEvent($eventId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            // Initialize Google Calendar service
            $calendar = new GoogleCalendarService($user);
            $service = $calendar->getService(); // Uses your working getService()

            // Delete the event
            $service->events->delete('primary', $eventId);

            return response()->json([
                'message' => 'Event deleted successfully'
            ]);
        } catch (\Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $code = $e->getCode();

            if ($code == 404) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            return response()->json([
                'message' => 'Failed to delete event',
                'error' => $error['error']['message'] ?? 'Google API error'
            ], $code);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
