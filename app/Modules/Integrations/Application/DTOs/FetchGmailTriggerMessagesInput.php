<?php

namespace App\Modules\Integrations\Application\DTOs;

final class FetchGmailTriggerMessagesInput
{
    /**
     * @param  array<int, string>  $labelIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?int $connectionId,
        public readonly int $afterEpochSeconds,
        public readonly array $labelIds = [],
        public readonly ?string $query = null,
    ) {}
}
