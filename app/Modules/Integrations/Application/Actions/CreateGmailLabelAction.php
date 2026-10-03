<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\CreateGmailLabelInput;
use App\Modules\Integrations\Application\DTOs\CreateGmailLabelResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailLabelCreator;

final class CreateGmailLabelAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailLabelCreator $creator,
    ) {}

    public function execute(CreateGmailLabelInput $input): CreateGmailLabelResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $result = $this->creator->create($resolved, $input->name);

        if ($result === null) {
            return new CreateGmailLabelResult(created: false);
        }

        return new CreateGmailLabelResult(
            created: true,
            labelId: $result['label_id'] ?? null,
            name: $result['name'] ?? null,
        );
    }
}
