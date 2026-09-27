<?php

namespace App\Modules\Execution\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RunWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'trigger_payload' => 'nullable|array',
            'idempotency_key' => [
                'nullable',
                'string',
                'max:191',
                $this->idempotencyKeyMustNotUseReservedPrefix(),
            ],
        ];
    }

    private function idempotencyKeyMustNotUseReservedPrefix(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (str_starts_with($value, 'schedule:')) {
                $fail('The idempotency key uses a reserved namespace prefix.');
            }

            if (str_starts_with($value, 'gmail:')) {
                $fail('The idempotency key uses a reserved namespace prefix.');
            }
        };
    }
}
