<?php

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date_format:Y-m-d H:i:s',
            'end' => 'required|date_format:Y-m-d H:i:s|after:start',
            'attendees' => 'nullable|array',
            'attendees.*' => 'email',
            'email_notification.send' => 'boolean',
            'email_notification.subject' => 'nullable|string',
            'email_notification.body' => 'nullable|string',
            'connection_id' => 'nullable|integer|min:1',
        ];
    }
}
