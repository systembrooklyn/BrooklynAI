<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\DeleteDocumentInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class DeleteDocumentAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(DeleteDocumentInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->deleteDocument($resolved, $input->documentId);
    }
}
