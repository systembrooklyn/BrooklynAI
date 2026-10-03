<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\CreateGmailDraftInput;
use App\Modules\Integrations\Application\DTOs\CreateGmailDraftResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;

final class CreateGmailDraftAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailEmailSender $sender,
    ) {}

    public function execute(CreateGmailDraftInput $input): CreateGmailDraftResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $result = $this->sender->createDraft(
            credentials: $resolved,
            fromEmail: $input->fromEmail,
            to: $input->to,
            subject: $input->subject,
            htmlBody: $input->htmlBody,
        );

        if ($result === null) {
            return new CreateGmailDraftResult(created: false);
        }

        return new CreateGmailDraftResult(
            created: true,
            draftId: $result['draft_id'] ?? null,
            messageId: $result['message_id'] ?? null,
        );
    }
}
