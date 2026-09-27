<?php

namespace App\Modules\Automation\Application\Services;

use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Integrations\Application\Services\CapabilityScopeMap;

final class WorkflowCapabilityValidator
{
    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly CapabilityScopeMap $capabilities,
    ) {}

    /**
     * Validate a single trigger or action definition against the caller's connections.
     *
     * Scope requirements come from the calling definition's `requiredScopes`,
     * NOT from the capability's entry in CapabilityScopeMap. This keeps a
     * specific action (e.g. Gmail send_email) from being forced to require
     * unrelated scopes (e.g. Gmail readonly) that only other definitions
     * under the same capability may need.
     *
     * The capability itself is still consulted, but only to confirm that the
     * backend recognizes the capability at all.
     *
     * Definitions that declare no capability are out of 7.4 scope and are
     * skipped entirely.
     *
     * @param  array<int, string>  $requiredScopes
     *
     * @throws WorkflowCapabilityException
     */
    public function validate(
        string $location,
        ?string $capability,
        array $requiredScopes,
        ?int $connectionId,
        int $userId,
    ): void {
        // Out of 7.4 scope: definitions that declare no capability are not gated here.
        if ($capability === null) {
            return;
        }

        // Rule E: capability is declared but unknown to the backend — fail loudly.
        if (! $this->capabilities->has($capability)) {
            throw WorkflowCapabilityException::unsupportedCapability($location, $capability);
        }

        // Rule A: a connection is required for capability-gated definitions.
        if ($connectionId === null) {
            throw WorkflowCapabilityException::missingConnection($location);
        }

        // Rules B / C: connection must exist and be owned by the caller.
        // findForUser is documented as user-scoped; the userId re-check is
        // defence-in-depth against a misbehaving repository implementation.
        $connection = $this->connections->findForUser($userId, $connectionId);

        if ($connection === null || $connection->userId !== $userId) {
            throw WorkflowCapabilityException::connectionUnavailable($location, $connectionId);
        }

        // Rule F: every scope required by this specific definition must be present.
        $missingScopes = array_values(array_diff($requiredScopes, $connection->scopes));

        if ($missingScopes !== []) {
            throw WorkflowCapabilityException::missingScopes($location, $missingScopes);
        }
    }
}
