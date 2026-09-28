<?php

namespace App\Modules\Execution\Application\Services;

use App\Modules\Execution\Application\DTOs\TickResult;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class SchedulerTickService
{
    private const LOCK_KEY = 'scheduler-tick-global';

    public function __construct(
        private readonly TriggerCoordinator $coordinator,
        private readonly ExecutionRepository $executions,
    ) {}

    public function tick(): TickResult
    {
        $ttl = max(30, (int) config('internal_scheduler.lock_seconds', 60));
        $batchSize = max(1, (int) config('internal_scheduler.batch_size', 50));
        $staleMinutes = max(1, (int) config('internal_scheduler.stale_running_minutes', 5));

        $lock = Cache::lock(self::LOCK_KEY, $ttl);

        if (! $lock->get()) {
            return new TickResult(
                processedCount: 0,
                executedCount: 0,
                skippedCount: 0,
                failedCount: 0,
                lockHeld: true,
            );
        }

        try {
            $this->sweepStaleRunning($staleMinutes);

            return $this->coordinator->run(new DateTimeImmutable, $batchSize);
        } finally {
            $lock->release();
        }
    }

    private function sweepStaleRunning(int $staleMinutes): void
    {
        try {
            $threshold = (new DateTimeImmutable)->modify("-{$staleMinutes} minutes");
            $affected = $this->executions->markStaleRunningAsFailed($threshold);

            if ($affected > 0) {
                Log::warning('Scheduler tick: marked stale running executions as failed', [
                    'count' => $affected,
                    'threshold_minutes' => $staleMinutes,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Scheduler tick: stale running sweep failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
