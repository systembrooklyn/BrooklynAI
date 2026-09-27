<?php

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppendRowByHeadersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sheet_name' => 'required|string',
            'data' => 'required|array',
            'data.*' => 'nullable|string',
            'connection_id' => 'nullable|integer|min:1',
        ];
    }
}
