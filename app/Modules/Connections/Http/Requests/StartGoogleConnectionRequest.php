<?php

namespace App\Modules\Connections\Http\Requests;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StartGoogleConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'capability' => ['nullable', 'string', 'max:64', $this->capabilityMustBeKnown()],
        ];
    }

    private function capabilityMustBeKnown(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value)) {
                return;
            }

            if (! app(CapabilityScopeMap::class)->has($value)) {
                $fail('Unknown capability.');
            }
        };
    }
}
