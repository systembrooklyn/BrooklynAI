<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GenerateFromTemplateInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;

final class GenerateFromTemplateAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
    ) {}

    public function execute(GenerateFromTemplateInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->docs->createPersonalizedDoc(
            $resolved,
            [
                'name' => $input->name,
                'service' => $input->service,
                'sign' => $input->sign,
            ],
            $input->title ?? 'Personalized Letter',
        );
    }
}
