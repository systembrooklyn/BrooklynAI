<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\AppendTextInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class AppendTextAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(AppendTextInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->appendText($resolved, $input->documentId, $input->text);
    }
}
