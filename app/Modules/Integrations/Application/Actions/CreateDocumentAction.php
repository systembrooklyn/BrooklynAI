<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\CreateDocumentInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class CreateDocumentAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(CreateDocumentInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->createDocument($resolved, $input->title);
    }
}
