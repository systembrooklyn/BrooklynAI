<?php

namespace App\Modules\Integrations\Application\Services;

use InvalidArgumentException;

/**
 * Backend-owned capability catalog.
 *
 * Maps a capability key to the OAuth scopes requested at connection
 * time, when a user authorizes an external account for that
 * capability (see StartGoogleConnectionAction).
 *
 * Also used to validate that a capability referenced by a workflow
 * definition is one the backend currently supports
 * (see WorkflowCapabilityValidator::validate()).
 *
 * It is intentionally NOT the source of truth for the scopes required
 * by a specific workflow step. That responsibility belongs to the
 * corresponding ActionDefinition::requiredScopes or
 * TriggerDefinition::requiredScopes. Keeping those separate means a
 * specific action (e.g. Gmail send_email) is required only to possess
 * its own scope (gmail.send) rather than every scope other Gmail
 * definitions may need.
 */
final class CapabilityScopeMap
{
    /**
     * @var array<string, array<int, string>>
     */
    private const CAPABILITIES = [
        'gmail' => [
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.compose',
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/gmail.labels',
        ],
    ];

    public function has(string $capability): bool
    {
        return array_key_exists($capability, self::CAPABILITIES);
    }

    /**
     * @return array<int, string>
     */
    public function resolve(string $capability): array
    {
        if (! $this->has($capability)) {
            throw new InvalidArgumentException(
                sprintf('Unknown capability "%s".', $capability)
            );
        }

        return self::CAPABILITIES[$capability];
    }

    /**
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return array_keys(self::CAPABILITIES);
    }
}
