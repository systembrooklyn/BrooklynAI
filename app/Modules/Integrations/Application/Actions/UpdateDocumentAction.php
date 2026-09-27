<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\UpdateDocumentInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class UpdateDocumentAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(UpdateDocumentInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->updateDocument($resolved, $input->documentId, $input->content);
    }
}
