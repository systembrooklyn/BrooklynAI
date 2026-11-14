<?php

namespace App\Modules\Execution\Application\Actions;

use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\TestEmailUnavailableException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use InvalidArgumentException;

final class GenerateTestPayloadAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageReader $reader,
        private readonly GmailMessagePayloadBuilder $payloadBuilder,
    ) {}

    /**
     * Phase 1 — disabled Gmail read path.
     *
     * This action required gmail.readonly (Restricted) to auto-generate a
     * sample Gmail trigger payload for "Try flow now" testing. The entire
     * implementation is preserved below in comments. When Gmail triggers
     * are reactivated, uncomment the implementation, re-enable the
     * corresponding Gmail trigger definitions, and re-enable
     * gmail.readonly in CapabilityScopeMap.
     *
     * The class and constructor are preserved so that existing imports
     * (and downstream service bindings) do not break. Calling execute()
     * while the feature is disabled throws a RuntimeException that the
     * caller in WorkflowExecutionController converts into an HTTP 422.
     *
     * @return array<string, mixed>
     */
    public function execute(int $userId, int $workflowId): array
    {
        /*
        $workflow = $this->workflows->findForUser($userId, $workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($userId, $workflowId);
        }

        $trigger = $this->workflows->findTriggerForWorkflow($workflowId);

        if ($trigger === null || $trigger->integrationKey !== 'google.gmail') {
            throw new InvalidArgumentException(
                'Automatic test payload generation is only supported for workflows with a Google Gmail trigger.'
            );
        }

        $credentials = $this->credentials->resolve($userId, $trigger->connectionId);

        // Only look at emails from the last 10 minutes to accurately simulate "new" emails
        $afterEpochSeconds = (new \DateTimeImmutable)->modify('-10 minutes')->getTimestamp();

        // If the trigger requires attachments, ensure the test email has one
        $query = $trigger->triggerKey === 'new_email_received_with_attachment' ? 'has:attachment' : null;

        $messageIds = $this->reader->listMessageIds($credentials, $afterEpochSeconds, [], $query, 1);

        if (empty($messageIds)) {
            if ($query === 'has:attachment') {
                throw TestEmailUnavailableException::noEmailsWithAttachments();
            }
            throw TestEmailUnavailableException::noEmailsInInbox();
        }

        $latestMessageId = $messageIds[0];
        $message = $this->reader->getMessage($credentials, $latestMessageId);

        return $this->payloadBuilder->build($message);
        */

        throw new \RuntimeException(
            'Gmail test payload generation is disabled in the current send-only release. Provide trigger_payload explicitly, or reconnect Gmail after this feature is reactivated.'
        );
    }
}
