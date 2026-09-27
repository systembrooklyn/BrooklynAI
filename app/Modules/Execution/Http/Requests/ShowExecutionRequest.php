<?php

namespace App\Modules\Execution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
