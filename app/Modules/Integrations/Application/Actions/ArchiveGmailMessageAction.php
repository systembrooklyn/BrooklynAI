<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ModifyGmailMessageInput;
use App\Modules\Integrations\Application\DTOs\ModifyGmailMessageResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageModifier;

final class ArchiveGmailMessageAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageModifier $modifier,
    ) {}

    public function execute(ModifyGmailMessageInput $input): ModifyGmailMessageResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $this->modifier->archive($resolved, $input->messageId);

        return new ModifyGmailMessageResult(
            modified: true,
            messageId: $input->messageId,
        );
    }
}
