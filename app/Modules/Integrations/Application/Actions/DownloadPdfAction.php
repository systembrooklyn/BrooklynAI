<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\DownloadPdfInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class DownloadPdfAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(DownloadPdfInput $input): string
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->getDocAsPdfBinary($resolved, $input->documentId);
    }
}
