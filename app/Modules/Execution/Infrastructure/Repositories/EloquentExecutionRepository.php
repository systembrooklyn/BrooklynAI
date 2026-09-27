<?php

namespace App\Modules\Execution\Infrastructure\Repositories;

use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionStepModel;
use DateTimeImmutable;
use DateTimeInterface;

final class EloquentExecutionRepository implements ExecutionRepository
{
    public function findForUser(int $userId, int $executionId): ?Execution
    {
        $model = ExecutionModel::query()
            ->where('user_id', $userId)
            ->where('id', $executionId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listForWorkflow(int $workflowId, int $limit = 50): array
    {
        return ExecutionModel::query()
            ->where('workflow_id', $workflowId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ExecutionModel $m) => $this->toEntity($m))
            ->all();
    }

    public function findByIdempotencyKey(int $workflowId, string $idempotencyKey): ?Execution
    {
        $model = ExecutionModel::query()
            ->where('workflow_id', $workflowId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findLastScheduledForWorkflow(int $workflowId): ?Execution
    {
        $model = ExecutionModel::query()
            ->where('workflow_id', $workflowId)
            ->where('trigger_source', 'schedule')
            ->orderByDesc('id')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function hasInProgressForWorkflow(int $workflowId): bool
    {
        return ExecutionModel::query()
            ->where('workflow_id', $workflowId)
            ->whereIn('status', [
                ExecutionStatus::Pending->value,
                ExecutionStatus::Running->value,
            ])
            ->exists();
    }

    public function save(Execution $execution, ?int $retryOfId = null): Execution
    {
        $isNew = $execution->id === null;

        $model = $isNew
            ? new ExecutionModel
            : ExecutionModel::query()->findOrFail($execution->id);

        $model->workflow_id = $execution->workflowId;
        $model->user_id = $execution->userId;
        $model->status = $execution->status->value;
        $model->trigger_source = $execution->triggerSource;
        $model->trigger_payload = $execution->triggerPayload;
        $model->workflow_snapshot = $execution->workflowSnapshot;
        $model->idempotency_key = $execution->idempotencyKey;
        $model->error_message = $execution->errorMessage;
        $model->started_at = $execution->startedAt;
        $model->finished_at = $execution->finishedAt;

        if ($isNew) {
            $model->retry_of_id = $retryOfId;
        }

        $model->save();

        return $this->toEntity($model->fresh());
    }

    public function listStepsForExecution(int $executionId): array
    {
        return ExecutionStepModel::query()
            ->where('execution_id', $executionId)
            ->orderBy('position')
            ->get()
            ->map(fn (ExecutionStepModel $m) => $this->stepToEntity($m))
            ->all();
    }

    public function saveStep(ExecutionStep $step): ExecutionStep
    {
        $model = $step->id
            ? ExecutionStepModel::query()->findOrFail($step->id)
            : new ExecutionStepModel;

        $model->execution_id = $step->executionId;
        $model->position = $step->position;
        $model->step_snapshot = $step->stepSnapshot;
        $model->status = $step->status->value;
        $model->input = $step->input;
        $model->output = $step->output;
        $model->error_message = $step->errorMessage;
        $model->started_at = $step->startedAt;
        $model->finished_at = $step->finishedAt;
        $model->save();

        return $this->stepToEntity($model->fresh());
    }

    public function listFailedPollExecutionRoots(
        int $limit,
        int $maxRetries,
        DateTimeImmutable $olderThan,
    ): array {
        return ExecutionModel::query()
            ->where('trigger_source', 'poll')
            ->where('status', ExecutionStatus::Failed->value)
            ->where('retry_attempts', '<', $maxRetries)
            ->where('updated_at', '<', $olderThan)
            ->where('idempotency_key', 'not like', '%#r%')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (ExecutionModel $m) => $this->toEntity($m))
            ->all();
    }

    public function claimFailedPollExecution(
        int $executionId,
        int $observedRetryAttempts,
        int $maxRetries,
    ): bool {
        if ($observedRetryAttempts >= $maxRetries) {
            return false;
        }

        $affected = ExecutionModel::query()
            ->where('id', $executionId)
            ->where('status', ExecutionStatus::Failed->value)
            ->where('retry_attempts', $observedRetryAttempts)
            ->update([
                'retry_attempts' => $observedRetryAttempts + 1,
                'updated_at' => now(),
            ]);

        return $affected === 1;
    }

    private function toEntity(ExecutionModel $model): Execution
    {
        return new Execution(
            id: (int) $model->id,
            workflowId: (int) $model->workflow_id,
            userId: (int) $model->user_id,
            status: ExecutionStatus::from($model->status),
            triggerSource: (string) $model->trigger_source,
            triggerPayload: is_array($model->trigger_payload) ? $model->trigger_payload : null,
            workflowSnapshot: is_array($model->workflow_snapshot) ? $model->workflow_snapshot : [],
            idempotencyKey: $model->idempotency_key,
            errorMessage: $model->error_message,
            startedAt: $this->toImmutable($model->started_at),
            finishedAt: $this->toImmutable($model->finished_at),
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
            retryAttempts: (int) ($model->retry_attempts ?? 0),
        );
    }

    private function stepToEntity(ExecutionStepModel $model): ExecutionStep
    {
        return new ExecutionStep(
            id: (int) $model->id,
            executionId: (int) $model->execution_id,
            position: (int) $model->position,
            stepSnapshot: is_array($model->step_snapshot) ? $model->step_snapshot : [],
            status: ExecutionStepStatus::from($model->status),
            input: is_array($model->input) ? $model->input : null,
            output: is_array($model->output) ? $model->output : null,
            errorMessage: $model->error_message,
            startedAt: $this->toImmutable($model->started_at),
            finishedAt: $this->toImmutable($model->finished_at),
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        return $value === null ? null : DateTimeImmutable::createFromInterface($value);
    }
}
