<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetReportInput;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;

final class GetReportAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleAnalyticsClient $analytics,
    ) {}

    public function execute(GetReportInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->analytics->getReport(
            $resolved,
            $input->propertyId,
            $input->dimensions,
            $input->metrics,
            $input->startDate,
            $input->endDate,
        );
    }
}
