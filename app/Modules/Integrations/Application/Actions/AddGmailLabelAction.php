<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ModifyGmailLabelsInput;
use App\Modules\Integrations\Application\DTOs\ModifyGmailLabelsResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageModifier;

final class AddGmailLabelAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageModifier $modifier,
    ) {}

    public function execute(ModifyGmailLabelsInput $input): ModifyGmailLabelsResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $this->modifier->addLabel($resolved, $input->messageId, $input->labelId);

        return new ModifyGmailLabelsResult(
            modified: true,
            messageId: $input->messageId,
            labelId: $input->labelId,
        );
    }
}
