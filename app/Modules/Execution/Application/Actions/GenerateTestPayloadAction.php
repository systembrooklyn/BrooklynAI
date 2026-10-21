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
     * @return array<string, mixed>
     */
    public function execute(int $userId, int $workflowId): array
    {
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

        $query = $trigger->triggerKey === 'new_email_received_with_attachment' ? 'has:attachment' : null;

        $messageIds = $this->reader->listMessageIds($credentials, 0, [], $query, 1);

        if (empty($messageIds)) {
            if ($query === 'has:attachment') {
                throw TestEmailUnavailableException::noEmailsWithAttachments();
            }
            throw TestEmailUnavailableException::noEmailsInInbox();
        }

        $latestMessageId = $messageIds[0];
        $message = $this->reader->getMessage($credentials, $latestMessageId);

        return $this->payloadBuilder->build($message);
    }
}
