<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetRealtimeOverviewInput;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;

final class GetRealtimeOverviewAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleAnalyticsClient $analytics,
    ) {}

    public function execute(GetRealtimeOverviewInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->analytics->getRealtimeOverview($resolved, $input->propertyId);
    }
}
