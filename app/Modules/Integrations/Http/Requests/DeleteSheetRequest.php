<?php

namespace App\Modules\Integrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sheet_id' => 'required|integer',
            'connection_id' => 'nullable|integer|min:1',
        ];
    }
}
