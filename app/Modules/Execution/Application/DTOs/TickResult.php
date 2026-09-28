<?php

namespace App\Modules\Execution\Application\DTOs;

final class TickResult
{
    public function __construct(
        public readonly int $processedCount,
        public readonly int $executedCount,
        public readonly int $skippedCount,
        public readonly int $failedCount,
        public readonly bool $lockHeld = false,
    ) {}

    public function toArray(): array
    {
        return [
            'ok' => true,
            'processed' => $this->processedCount,
            'executed' => $this->executedCount,
            'skipped' => $this->skippedCount,
            'failed' => $this->failedCount,
            'lock_held' => $this->lockHeld,
        ];
    }
}
