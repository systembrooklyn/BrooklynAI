<?php

namespace App\Modules\Execution\Application\Services;

use App\Modules\Execution\Application\Contracts\TriggerStrategy;

final class TriggerStrategyRegistry
{
    /** @var array<string, TriggerStrategy> */
    private array $strategies = [];

    public function register(TriggerStrategy $strategy): void
    {
        $this->strategies[$strategy->strategyKey()] = $strategy;
    }

    public function resolve(string $key): ?TriggerStrategy
    {
        return $this->strategies[$key] ?? null;
    }
}
