<?php

namespace App\Modules\Automation\Infrastructure\Repositories;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use DateTimeImmutable;
use DateTimeInterface;

final class EloquentWorkflowRepository implements WorkflowRepository
{
    public function findForUser(int $userId, int $workflowId, bool $includeTrashed = false): ?Workflow
    {
        $query = WorkflowModel::query()
            ->where('user_id', $userId)
            ->where('id', $workflowId);

        if ($includeTrashed) {
            $query->withTrashed();
        }

        $model = $query->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listForUser(int $userId, bool $onlyTrashed = false): array
    {
        $query = WorkflowModel::query()->where('user_id', $userId);

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(fn(WorkflowModel $m) => $this->toEntity($m))
            ->all();
    }

    public function listActiveWithTriggerStrategy(string $strategy): array
    {
        return $this->hydrateActivePairs(
            WorkflowTriggerModel::query()->where('strategy', $strategy)->get()
        );
    }

    public function listActiveWithTrigger(string $integrationKey, string $triggerKey): array
    {
        return $this->hydrateActivePairs(
            WorkflowTriggerModel::query()
                ->where('integration_key', $integrationKey)
                ->where('trigger_key', $triggerKey)
                ->get()
        );
    }

    public function listActiveWithTriggerDue(
        string $integrationKey,
        string $triggerKey,
        DateTimeImmutable $now,
    ): array {
        return $this->hydrateActivePairs(
            WorkflowTriggerModel::query()
                ->where('integration_key', $integrationKey)
                ->where('trigger_key', $triggerKey)
                ->where(function ($q) use ($now) {
                    $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', $now);
                })
                ->get()
        );
    }

    public function save(Workflow $workflow): Workflow
    {
        $model = $workflow->id
            ? WorkflowModel::withTrashed()->findOrFail($workflow->id)
            : new WorkflowModel;

        $model->user_id = $workflow->userId;
        $model->name = $workflow->name;
        $model->description = $workflow->description;
        $model->status = $workflow->status->value;
        $model->save();

        return $this->toEntity($model->fresh());
    }

    public function softDelete(Workflow $workflow): void
    {
        if ($workflow->id === null) {
            return;
        }
        WorkflowModel::query()->where('id', $workflow->id)->delete();
    }

    public function restore(Workflow $workflow): void
    {
        if ($workflow->id === null) {
            return;
        }
        WorkflowModel::withTrashed()->where('id', $workflow->id)->restore();
    }

    public function findTriggerForWorkflow(int $workflowId): ?WorkflowTrigger
    {
        $model = WorkflowTriggerModel::query()->where('workflow_id', $workflowId)->first();

        return $model ? $this->triggerToEntity($model) : null;
    }

    public function saveTrigger(WorkflowTrigger $trigger): WorkflowTrigger
    {
        $model = $trigger->id
            ? WorkflowTriggerModel::query()->findOrFail($trigger->id)
            : new WorkflowTriggerModel;

        $model->workflow_id = $trigger->workflowId;
        $model->integration_key = $trigger->integrationKey;
        $model->trigger_key = $trigger->triggerKey;
        $model->connection_id = $trigger->connectionId;
        $model->strategy = $trigger->strategy;
        $model->config = $trigger->config;
        $model->interval_minutes = $trigger->intervalMinutes;
        $model->poll_cursor = $trigger->pollCursor;
        $model->next_poll_at = $trigger->nextPollAt;
        $model->save();

        return $this->triggerToEntity($model->fresh());
    }

    public function deleteTrigger(WorkflowTrigger $trigger): void
    {
        if ($trigger->id === null) {
            return;
        }
        WorkflowTriggerModel::query()->where('id', $trigger->id)->delete();
    }

    public function listStepsForWorkflow(int $workflowId): array
    {
        return WorkflowStepModel::query()
            ->where('workflow_id', $workflowId)
            ->orderBy('position')
            ->get()
            ->map(fn(WorkflowStepModel $m) => $this->stepToEntity($m))
            ->all();
    }

    public function saveStep(WorkflowStep $step): WorkflowStep
    {
        $model = $step->id
            ? WorkflowStepModel::query()->findOrFail($step->id)
            : new WorkflowStepModel;

        $model->workflow_id = $step->workflowId;
        $model->position = $step->position;
        $model->integration_key = $step->integrationKey;
        $model->action_key = $step->actionKey;
        $model->connection_id = $step->connectionId;
        $model->config = $step->config;
        $model->save();

        return $this->stepToEntity($model->fresh());
    }

    public function deleteStep(WorkflowStep $step): void
    {
        if ($step->id === null) {
            return;
        }
        WorkflowStepModel::query()->where('id', $step->id)->delete();
    }

    public function deleteStepsForWorkflow(int $workflowId): void
    {
        WorkflowStepModel::query()->where('workflow_id', $workflowId)->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WorkflowTriggerModel>  $triggerModels
     * @return array<int, array{workflow: Workflow, trigger: WorkflowTrigger}>
     */
    private function hydrateActivePairs($triggerModels): array
    {
        if ($triggerModels->isEmpty()) {
            return [];
        }

        $workflowIds = $triggerModels->pluck('workflow_id')->unique()->all();

        $workflowModels = WorkflowModel::query()
            ->whereIn('id', $workflowIds)
            ->where('status', WorkflowStatus::Active->value)
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($triggerModels as $triggerModel) {
            $workflowModel = $workflowModels->get($triggerModel->workflow_id);
            if ($workflowModel === null) {
                continue;
            }

            $result[] = [
                'workflow' => $this->toEntity($workflowModel),
                'trigger' => $this->triggerToEntity($triggerModel),
            ];
        }

        return $result;
    }

    private function toEntity(WorkflowModel $model): Workflow
    {
        return new Workflow(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            name: (string) $model->name,
            description: $model->description,
            status: WorkflowStatus::from($model->status),
            deletedAt: $this->toImmutable($model->deleted_at),
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function triggerToEntity(WorkflowTriggerModel $model): WorkflowTrigger
    {
        return new WorkflowTrigger(
            id: (int) $model->id,
            workflowId: (int) $model->workflow_id,
            integrationKey: (string) $model->integration_key,
            triggerKey: (string) $model->trigger_key,
            connectionId: $model->connection_id !== null ? (int) $model->connection_id : null,
            strategy: (string) $model->strategy,
            config: is_array($model->config) ? $model->config : [],
            intervalMinutes: $model->interval_minutes !== null ? (int) $model->interval_minutes : null,
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
            pollCursor: $model->poll_cursor !== null ? (int) $model->poll_cursor : null,
            nextPollAt: $this->toImmutable($model->next_poll_at),
        );
    }

    private function stepToEntity(WorkflowStepModel $model): WorkflowStep
    {
        return new WorkflowStep(
            id: (int) $model->id,
            workflowId: (int) $model->workflow_id,
            position: (int) $model->position,
            integrationKey: (string) $model->integration_key,
            actionKey: (string) $model->action_key,
            connectionId: $model->connection_id !== null ? (int) $model->connection_id : null,
            config: is_array($model->config) ? $model->config : [],
            createdAt: $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            updatedAt: $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        return $value === null ? null : DateTimeImmutable::createFromInterface($value);
    }
    public function listAllActiveWithTriggerDue(DateTimeImmutable $now, int $limit = 50): array
    {
        return $this->hydrateActivePairs(
            WorkflowTriggerModel::query()
                ->where(function ($q) use ($now) {
                    $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', $now);
                })
                ->orderBy('next_poll_at')
                ->limit($limit)
                ->get()
        );
    }
}
