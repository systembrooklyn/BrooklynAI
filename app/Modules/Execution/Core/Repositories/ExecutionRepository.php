<?php

namespace App\Modules\Execution\Core\Repositories;

use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use DateTimeImmutable;

interface ExecutionRepository
{
    public function findForUser(int $userId, int $executionId): ?Execution;

    /**
     * @return Execution[]
     */
    public function listForWorkflow(int $workflowId, int $limit = 50): array;

    public function findByIdempotencyKey(int $workflowId, string $idempotencyKey): ?Execution;

    public function findLastScheduledForWorkflow(int $workflowId): ?Execution;

    public function hasInProgressForWorkflow(int $workflowId): bool;

    /**
     * @param  int|null  $retryOfId  Written only when creating a new execution.
     */
    public function save(Execution $execution, ?int $retryOfId = null): Execution;

    /**
     * @return ExecutionStep[]
     */
    public function listStepsForExecution(int $executionId): array;

    public function saveStep(ExecutionStep $step): ExecutionStep;

    /**
     * @return Execution[]
     */
    public function listFailedPollExecutionRoots(
        int $limit,
        int $maxRetries,
        DateTimeImmutable $olderThan,
    ): array;

    /**
     * CAS claim. Returns true iff this call performed the increment.
     */
    public function claimFailedPollExecution(
        int $executionId,
        int $observedRetryAttempts,
        int $maxRetries,
    ): bool;
}
