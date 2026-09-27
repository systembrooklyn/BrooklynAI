<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class WorkflowCapabilityException extends RuntimeException
{
    public const ERROR_MISSING_CONNECTION = 'missing_connection';

    public const ERROR_CONNECTION_NOT_FOUND = 'connection_not_found_or_not_owned';

    public const ERROR_UNSUPPORTED_CAPABILITY = 'unsupported_capability';

    public const ERROR_MISSING_SCOPES = 'missing_scopes';

    public const ERROR_UNKNOWN_INTEGRATION = 'unknown_integration';

    public const ERROR_UNKNOWN_TRIGGER = 'unknown_trigger';

    public const ERROR_UNKNOWN_ACTION = 'unknown_action';

    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly string $errorCode,
        private readonly array $context,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public static function missingConnection(string $location): self
    {
        return new self(
            self::ERROR_MISSING_CONNECTION,
            ['location' => $location],
            sprintf('%s requires a connection but none was provided.', $location),
        );
    }

    public static function connectionUnavailable(string $location, int $connectionId): self
    {
        return new self(
            self::ERROR_CONNECTION_NOT_FOUND,
            ['location' => $location, 'connection_id' => $connectionId],
            sprintf(
                '%s references connection %d which is not available for this user.',
                $location,
                $connectionId,
            ),
        );
    }

    public static function unsupportedCapability(string $location, string $capability): self
    {
        return new self(
            self::ERROR_UNSUPPORTED_CAPABILITY,
            ['location' => $location, 'capability' => $capability],
            sprintf('%s requires capability "%s" which is not supported.', $location, $capability),
        );
    }

    /**
     * @param  array<int, string>  $missingScopes
     */
    public static function missingScopes(string $location, array $missingScopes): self
    {
        return new self(
            self::ERROR_MISSING_SCOPES,
            ['location' => $location, 'missing_scopes' => array_values($missingScopes)],
            sprintf(
                '%s is missing required scopes: %s.',
                $location,
                implode(', ', $missingScopes),
            ),
        );
    }

    public static function unknownIntegration(string $location, string $integrationKey): self
    {
        return new self(
            self::ERROR_UNKNOWN_INTEGRATION,
            ['location' => $location, 'integration_key' => $integrationKey],
            sprintf('%s references integration "%s" which is not registered.', $location, $integrationKey),
        );
    }

    public static function unknownTrigger(string $location, string $integrationKey, string $triggerKey): self
    {
        return new self(
            self::ERROR_UNKNOWN_TRIGGER,
            [
                'location' => $location,
                'integration_key' => $integrationKey,
                'trigger_key' => $triggerKey,
            ],
            sprintf(
                '%s references trigger "%s" which is not registered for integration "%s".',
                $location,
                $triggerKey,
                $integrationKey,
            ),
        );
    }

    public static function unknownAction(string $location, string $integrationKey, string $actionKey): self
    {
        return new self(
            self::ERROR_UNKNOWN_ACTION,
            [
                'location' => $location,
                'integration_key' => $integrationKey,
                'action_key' => $actionKey,
            ],
            sprintf(
                '%s references action "%s" which is not registered for integration "%s".',
                $location,
                $actionKey,
                $integrationKey,
            ),
        );
    }
}
