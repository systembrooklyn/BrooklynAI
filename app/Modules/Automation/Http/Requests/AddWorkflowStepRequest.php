<?php

namespace App\Modules\Automation\Http\Requests;

use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AddWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'integration_key' => ['required', 'string', 'max:64', $this->integrationMustExist()],
            'action_key' => ['required', 'string', 'max:64', $this->actionMustExist()],
            'connection_id' => 'nullable|integer|min:1',
            'config' => 'nullable|array',
        ];
    }

    private function integrationMustExist(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (! app(IntegrationCatalog::class)->has($value)) {
                $fail('Unknown integration.');
            }
        };
    }

    private function actionMustExist(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $integrationKey = $this->input('integration_key');

            if (! is_string($integrationKey)) {
                return;
            }

            $integration = app(IntegrationCatalog::class)->find($integrationKey);

            if ($integration === null) {
                return;
            }

            foreach ($integration->actions as $action) {
                if ($action->key === $value) {
                    return;
                }
            }

            $fail('Unknown action for this integration.');
        };
    }
}
