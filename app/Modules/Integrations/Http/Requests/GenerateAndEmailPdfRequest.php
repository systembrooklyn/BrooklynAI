<?php

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAndEmailPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'to' => 'required|array',
            'to.*' => 'email',
            'subject' => 'required|string',
            'body' => 'nullable|string',
            'data' => 'required|array',
            'filename' => 'nullable|string|max:255',
            'connection_id' => 'nullable|integer|min:1',
        ];
    }
}
