<?php

namespace App\Modules\Automation\Core\Repositories;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use DateTimeImmutable;

interface WorkflowRepository
{
    public function findForUser(int $userId, int $workflowId, bool $includeTrashed = false): ?Workflow;

    /**
     * @return Workflow[]
     */
    public function listForUser(int $userId, bool $onlyTrashed = false): array;

    /**
     * @return array<int, array{workflow: Workflow, trigger: WorkflowTrigger}>
     */
    public function listActiveWithTriggerStrategy(string $strategy): array;

    /**
     * @return array<int, array{workflow: Workflow, trigger: WorkflowTrigger}>
     */
    public function listActiveWithTrigger(string $integrationKey, string $triggerKey): array;

    /**
     * @return array<int, array{workflow: Workflow, trigger: WorkflowTrigger}>
     */
    public function listActiveWithTriggerDue(
        string $integrationKey,
        string $triggerKey,
        DateTimeImmutable $now,
    ): array;

    public function save(Workflow $workflow): Workflow;

    public function softDelete(Workflow $workflow): void;

    public function restore(Workflow $workflow): void;

    public function findTriggerForWorkflow(int $workflowId): ?WorkflowTrigger;

    public function saveTrigger(WorkflowTrigger $trigger): WorkflowTrigger;

    public function deleteTrigger(WorkflowTrigger $trigger): void;

    /**
     * @return WorkflowStep[]
     */
    public function listStepsForWorkflow(int $workflowId): array;

    public function saveStep(WorkflowStep $step): WorkflowStep;

    public function deleteStep(WorkflowStep $step): void;

    public function deleteStepsForWorkflow(int $workflowId): void;
}
