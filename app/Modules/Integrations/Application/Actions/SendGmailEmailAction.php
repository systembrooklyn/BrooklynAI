<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\SendGmailEmailInput;
use App\Modules\Integrations\Application\DTOs\SendGmailEmailResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;

final class SendGmailEmailAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailEmailSender $sender,
    ) {}

    public function execute(SendGmailEmailInput $input): SendGmailEmailResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $messageId = $this->sender->send(
            credentials: $resolved,
            fromEmail: $input->fromEmail,
            to: $input->to,
            subject: $input->subject,
            htmlBody: $input->htmlBody,
        );

        return new SendGmailEmailResult(
            sent: true,
            messageId: $messageId,
        );
    }
}
