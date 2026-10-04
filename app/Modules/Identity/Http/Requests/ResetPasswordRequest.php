<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|string|email|max:255',
            'code'     => 'required|string|min:4|max:10',
            'password' => 'required|string|min:6|max:255',
        ];
    }
}
