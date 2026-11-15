<?php

namespace App\Modules\Execution\Application\Strategies;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Execution\Application\Contracts\TriggerStrategy;
use App\Modules\Execution\Application\DTOs\StrategyResult;
use DateTimeImmutable;

/**
 * Phase 3: dispatches poll-strategy triggers by integration_key.
 *
 * Gmail polling remains disabled in Phase 1 per the master plan. The
 * GmailPollStrategy class exists and is preserved, but is intentionally
 * NOT instantiated or delegated to here. When Gmail polling is re-enabled
 * in a future phase, this wrapper should be extended to delegate to it.
 */
final class PollStrategy implements TriggerStrategy
{
    public function __construct(
        private readonly GoogleDriveChangePollStrategy $drive,
    ) {}

    public function strategyKey(): string
    {
        return 'poll';
    }

    public function process(
        Workflow $workflow,
        WorkflowTrigger $trigger,
        DateTimeImmutable $now,
    ): StrategyResult {
        return match ($trigger->integrationKey) {
            'google.drive' => $this->drive->process($workflow, $trigger, $now),
            default => StrategyResult::skipped('unsupported_poll_integration'),
        };
    }
}
