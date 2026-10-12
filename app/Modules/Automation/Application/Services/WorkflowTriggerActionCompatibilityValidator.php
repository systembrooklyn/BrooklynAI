<?php

namespace App\Modules\Automation\Application\Services;

use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;

/**
 * Enforces the pairing rules between a workflow's trigger and the actions
 * used by its steps.
 *
 * An ActionDefinition may declare `allowedTriggerKeys`, a list of qualified
 * trigger keys ("<integration_key>.<trigger_key>") it may be paired with.
 * Actions that declare no restriction (null or empty) are unrestricted and
 * preserve their existing behavior.
 *
 * This validator is intentionally narrow: it only checks the pairing rule.
 * Catalog-membership, capability support, and scope requirements remain the
 * responsibility of the existing validators and callers.
 */
final class WorkflowTriggerActionCompatibilityValidator
{
    public function __construct(
        private readonly IntegrationCatalog $catalog,
    ) {}

    /**
     * @param  string  $location  Free-form location label used in the error context.
     *
     * @throws WorkflowCapabilityException When the pairing is not permitted.
     */
    public function validate(
        string $location,
        string $actionIntegrationKey,
        string $actionKey,
        ?string $triggerIntegrationKey,
        ?string $triggerKey,
    ): void {
        $integration = $this->catalog->find($actionIntegrationKey);

        if ($integration === null) {
            // Catalog membership is validated by the caller. Nothing to enforce here.
            return;
        }

        $definition = null;

        foreach ($integration->actions as $candidate) {
            if ($candidate->key === $actionKey) {
                $definition = $candidate;
                break;
            }
        }

        if ($definition === null) {
            // Unknown actions are handled by the existing capability check.
            return;
        }

        $allowed = $definition->allowedTriggerKeys;

        if ($allowed === null || $allowed === []) {
            return;
        }

        $qualified = ($triggerIntegrationKey !== null && $triggerIntegrationKey !== ''
            && $triggerKey !== null && $triggerKey !== '')
            ? $triggerIntegrationKey.'.'.$triggerKey
            : null;

        if ($qualified === null || ! in_array($qualified, $allowed, true)) {
            throw WorkflowCapabilityException::incompatibleTrigger(
                location: $location,
                actionKey: $actionIntegrationKey.'.'.$actionKey,
                triggerKey: $qualified,
                allowedTriggerKeys: $allowed,
            );
        }
    }
}
