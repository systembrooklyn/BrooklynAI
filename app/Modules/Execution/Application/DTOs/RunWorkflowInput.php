<?php

namespace App\Modules\Execution\Application\DTOs;

final class RunWorkflowInput
{
    /**
     * @param  array<string, mixed>  $triggerPayload
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly array $triggerPayload,
        public readonly ?string $idempotencyKey,
        public readonly string $triggerSource = 'manual',
        public readonly ?int $retryOfId = null,
    ) {}
}
