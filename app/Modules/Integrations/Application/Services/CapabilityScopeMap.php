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
 *
 * Phase 1 — Gmail is limited to the send-only release. Only gmail.send
 * is requested. All other Gmail scopes are preserved in comments below
 * and can be restored by uncommenting when the corresponding Gmail
 * actions and triggers are re-enabled in GmailIntegration.php.
 */
final class CapabilityScopeMap
{
    /**
     * @var array<string, array<int, string>>
     */
    private const CAPABILITIES = [
        /*
         * Phase 1 — Gmail send-only.
         *
         * Only gmail.send is requested in the current release. The full
         * previous scope list (gmail.readonly, gmail.compose, gmail.modify,
         * gmail.labels) is preserved below for future reactivation of the
         * disabled Gmail actions and triggers.
         */
        'gmail' => [
            'https://www.googleapis.com/auth/gmail.send',
        ],
        /*
         * Phase 1: previous Gmail scope list, kept for reactivation.
         * Restore the additional Gmail scopes below ONLY after uncommenting the
         * corresponding ActionDefinition / TriggerDefinition entries in
         * GmailIntegration.php and the matching HandlerRegistry mappings
         * and GmailPollStrategy registration.
         *
         * 'gmail' => [
         *     'https://www.googleapis.com/auth/gmail.readonly',
         *     'https://www.googleapis.com/auth/gmail.send',
         *     'https://www.googleapis.com/auth/gmail.compose',
         *     'https://www.googleapis.com/auth/gmail.modify',
         *     'https://www.googleapis.com/auth/gmail.labels',
         * ],
         */

        'drive' => [
            'https://www.googleapis.com/auth/drive.file',
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
