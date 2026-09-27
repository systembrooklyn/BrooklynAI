<?php

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFromTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'service' => 'required|string',
            'sign' => 'required|string',
            'title' => 'nullable|string',
            'connection_id' => 'nullable|integer|min:1',
        ];
    }
}
