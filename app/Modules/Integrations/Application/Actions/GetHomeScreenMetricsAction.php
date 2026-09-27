<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetHomeScreenMetricsInput;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;

final class GetHomeScreenMetricsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleAnalyticsClient $analytics,
    ) {}

    public function execute(GetHomeScreenMetricsInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->analytics->getHomeScreenMetrics(
            $resolved,
            $input->propertyId,
            $input->startDate,
            $input->endDate,
        );
    }
}
