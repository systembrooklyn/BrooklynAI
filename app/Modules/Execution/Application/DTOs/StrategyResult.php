<?php

namespace App\Modules\Execution\Application\DTOs;

final class StrategyResult
{
    private function __construct(
        public readonly bool $executed,
        public readonly int $executionCount,
        public readonly ?string $reason,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(executed: false, executionCount: 0, reason: $reason);
    }

    public static function executed(int $count): self
    {
        return new self(executed: true, executionCount: $count, reason: null);
    }
}
