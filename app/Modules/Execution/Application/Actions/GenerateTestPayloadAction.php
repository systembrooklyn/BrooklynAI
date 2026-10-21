<?php

namespace App\Modules\Execution\Application\Actions;

use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use InvalidArgumentException;
use RuntimeException;

final class GenerateTestPayloadAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageReader $reader,
        private readonly GmailMessagePayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, int $workflowId): array
    {
        // SECURITY FIX: Ensure the user actually owns this workflow
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

        // If the workflow requires attachments, we must test with an email that has them
        $query = $trigger->triggerKey === 'new_email_received_with_attachment' ? 'has:attachment' : null;

        // Fetch ONLY the most recent message (limit = 1) to prevent timeouts
        $messageIds = $this->reader->listMessageIds($credentials, 0, [], $query, 1);

        if (empty($messageIds)) {
            $errorMsg = $query === 'has:attachment'
                ? 'No emails with attachments found in the connected Gmail account. Please send one first.'
                : 'No emails found in the connected Gmail account to use for testing. Please send an email first.';

            throw new RuntimeException($errorMsg);
        }

        $latestMessageId = $messageIds[0];
        $message = $this->reader->getMessage($credentials, $latestMessageId);

        return $this->payloadBuilder->build($message);
    }
}
