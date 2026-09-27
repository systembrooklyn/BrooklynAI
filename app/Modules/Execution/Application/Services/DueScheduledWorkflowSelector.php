<?php

namespace App\Modules\Execution\Application\Services;

use App\Modules\Execution\Core\Entities\Execution;
use DateTimeImmutable;

final class DueScheduledWorkflowSelector
{
    private const MIN_INTERVAL_MINUTES = 1;

    private const MAX_INTERVAL_MINUTES = 1440;

    public function isDue(
        ?Execution $lastScheduled,
        int $intervalMinutes,
        DateTimeImmutable $now,
    ): bool {
        if (! $this->isValidInterval($intervalMinutes)) {
            return false;
        }

        if ($lastScheduled === null) {
            return true;
        }

        $lastRunAt = $lastScheduled->startedAt ?? $lastScheduled->createdAt;
        $dueAt = $lastRunAt->modify('+'.$intervalMinutes.' minutes');

        return $dueAt <= $now;
    }

    public function dueAt(
        ?Execution $lastScheduled,
        int $intervalMinutes,
    ): ?DateTimeImmutable {
        if (! $this->isValidInterval($intervalMinutes) || $lastScheduled === null) {
            return null;
        }

        $lastRunAt = $lastScheduled->startedAt ?? $lastScheduled->createdAt;

        return $lastRunAt->modify('+'.$intervalMinutes.' minutes');
    }

    private function isValidInterval(int $intervalMinutes): bool
    {
        return $intervalMinutes >= self::MIN_INTERVAL_MINUTES
            && $intervalMinutes <= self::MAX_INTERVAL_MINUTES;
    }
}
