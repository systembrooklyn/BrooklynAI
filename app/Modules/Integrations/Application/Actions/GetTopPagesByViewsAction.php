<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetTopPagesByViewsInput;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;

final class GetTopPagesByViewsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleAnalyticsClient $analytics,
    ) {}

    public function execute(GetTopPagesByViewsInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->analytics->getTopPagesByViews($resolved, $input->propertyId, 10);
    }
}
