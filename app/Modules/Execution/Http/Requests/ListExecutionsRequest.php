<?php

namespace App\Modules\Execution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListExecutionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}
