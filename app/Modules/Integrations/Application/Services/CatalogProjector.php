<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Core\ValueObjects\FieldDefinition;

final class CatalogProjector
{
    public function __construct(
        private readonly IntegrationCatalog $catalog,
    ) {}

    /**
     * @return array{integrations: array<int, array<string, mixed>>}
     */
    public function project(): array
    {
        $integrations = array_map(
            fn (IntegrationDefinition $definition) => $this->projectIntegration($definition),
            $this->catalog->all(),
        );

        return ['integrations' => array_values($integrations)];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectIntegration(IntegrationDefinition $integration): array
    {
        return [
            'integration_key' => $integration->key->value,
            'provider_key' => $integration->providerKey->value,
            'name' => $this->resolve($integration->nameKey, $integration->name),
            'description' => $this->resolve($integration->descriptionKey, $integration->description),
            'category' => $integration->category,
            'auth' => [
                'type' => $integration->auth->type,
            ],
            'triggers' => array_values(array_map(
                fn (TriggerDefinition $trigger) => $this->projectTrigger($trigger),
                $integration->triggers,
            )),
            'actions' => array_values(array_map(
                fn (ActionDefinition $action) => $this->projectAction($action),
                $integration->actions,
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectTrigger(TriggerDefinition $trigger): array
    {
        return [
            'trigger_key' => $trigger->key,
            'label' => $this->resolve($trigger->labelKey, $trigger->label),
            'description' => $this->resolve($trigger->descriptionKey, $trigger->description),
            'strategy' => $trigger->strategy->value,
            'capability' => $trigger->capability,
            'config' => $this->projectConfig($trigger->fields),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAction(ActionDefinition $action): array
    {
        return [
            'action_key' => $action->key,
            'label' => $this->resolve($action->labelKey, $action->label),
            'description' => $this->resolve($action->descriptionKey, $action->description),
            'capability' => $action->capability,
            'config' => $this->projectConfig($action->fields),
        ];
    }

    /**
     * Empty configs must serialize as JSON `{}`, not `[]` (mobile contract §4.4).
     *
     * @param  array<int, FieldDefinition>  $fields
     * @return array<string, array<string, mixed>>|object
     */
    private function projectConfig(array $fields): array|object
    {
        $config = [];

        foreach ($fields as $field) {
            $config[$field->key] = $this->projectField($field);
        }

        return $config === [] ? (object) [] : $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectField(FieldDefinition $field): array
    {
        $projected = [
            'label' => $this->resolve($field->labelKey, $field->label),
            'type' => $field->type,
            'required' => $field->required,
        ];

        if ($field->descriptionKey !== null) {
            $projected['description'] = $this->resolve($field->descriptionKey, $field->description ?? '');
        } elseif ($field->description !== null) {
            $projected['description'] = $field->description;
        }

        if ($field->default !== null) {
            $projected['default'] = $field->default;
        }

        if ($field->options !== null) {
            $projected['options'] = array_values($field->options);
        }

        if ($field->options_source !== null) {
            $projected['options_source'] = $field->options_source;
        }

        return $projected;
    }

    /**
     * Resolve a translation key. When the key is null, empty, or returns itself
     * (Laravel's signal that the key is missing), fall back to the raw English.
     */
    private function resolve(?string $key, string $fallback): string
    {
        if ($key === null || $key === '') {
            return $fallback;
        }

        $translated = __($key);

        if (! is_string($translated) || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }
}
