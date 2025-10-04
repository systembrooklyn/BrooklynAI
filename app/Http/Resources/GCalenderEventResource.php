<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GCalenderEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected function formatGoogleDateTime(?string $dateTimeString): ?string
    {
        if (empty($dateTimeString)) {
            return null;
        }
        try {
            $carbon = Carbon::parse($dateTimeString);
            return $carbon->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
    public function toArray($request)
    {
        $start = $this->getStart();
        $end = $this->getEnd();

        return [
            'id' => $this->getId(),
            'summary' => $this->getSummary() ?? '(No title)',
            'description' => $this->getDescription(),
            'location' => $this->getLocation(),
            'start' => $start ? [
                'dateTime' => $this->formatGoogleDateTime($start->getDateTime()),
                'timeZone' => $start->getTimeZone(),
            ] : null,
            'end' => $end ? [
                'dateTime' => $this->formatGoogleDateTime($end->getDateTime()),
                'timeZone' => $end->getTimeZone(),
            ] : null,
            'attendees' => collect($this->getAttendees() ?? [])
                ->map(fn($a) => ['email' => $a->getEmail()])
                ->values()
                ->toArray(),
            'htmlLink' => $this->getHtmlLink() ?? null,
            'status' => $this->getStatus() ?? null,
            'created' => $this->getCreated() ?? null,
            'updated' => $this->getUpdated() ?? null,
        ];
    }
}
