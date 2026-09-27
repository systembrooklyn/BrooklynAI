<?php

namespace App\Modules\Automation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListWorkflowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'trashed' => 'nullable|string|in:true,false,1,0',
        ];
    }
}
