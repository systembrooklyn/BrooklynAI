<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ListPropertiesInput;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;

final class ListPropertiesAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleAnalyticsClient $analytics,
    ) {}

    public function execute(ListPropertiesInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->analytics->listProperties($resolved);
    }
}
