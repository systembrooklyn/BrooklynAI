<?php

namespace App\Modules\Execution\Application\Actions;

use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Exceptions\TemplateResolutionFailed;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\ExecutionContext;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Execution\Application\DTOs\ExecutionData;
use App\Modules\Execution\Application\DTOs\ExecutionStepData;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Application\DTOs\RunWorkflowResult;
use App\Modules\Execution\Application\Services\WorkflowSnapshotBuilder;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RunWorkflowAction
{
    private const MAX_ERROR_LENGTH = 2000;

    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ExecutionRepository $executions,
        private readonly TemplateResolver $templates,
        private readonly ActionInvoker $invoker,
        private readonly WorkflowSnapshotBuilder $snapshots,
    ) {}

    public function execute(RunWorkflowInput $input): RunWorkflowResult
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        // Manual execution is a deliberate user action.
        // Draft workflows are still being configured and must be testable
        // from the UI (see "Try flow now").
        // Paused workflows are explicitly stopped by the user.
        if ($workflow->status->isPaused()) {
            throw WorkflowNotExecutableException::forWorkflow(
                (int) $workflow->id,
                $workflow->status->value,
            );
        }

        if ($input->idempotencyKey !== null) {
            $existing = $this->executions->findByIdempotencyKey(
                $input->workflowId,
                $input->idempotencyKey,
            );

            if ($existing !== null) {
                return $this->hydrate($existing, replayed: true);
            }
        }

        if ($this->executions->hasInProgressForWorkflow($input->workflowId)) {
            throw ExecutionAlreadyRunningException::forWorkflow($input->workflowId);
        }

        $trigger = $this->workflows->findTriggerForWorkflow($input->workflowId);
        $steps = $this->workflows->listStepsForWorkflow($input->workflowId);

        $snapshot = $this->snapshots->build($workflow, $trigger, $steps);

        $now = new DateTimeImmutable;

        $execution = new Execution(
            id: null,
            workflowId: $input->workflowId,
            userId: $input->userId,
            status: ExecutionStatus::Pending,
            triggerSource: $input->triggerSource,
            triggerPayload: $input->triggerPayload,
            workflowSnapshot: $snapshot,
            idempotencyKey: $input->idempotencyKey,
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        // try {
        //     $execution = $this->executions->save($execution);
        // } catch (QueryException $e) {
        try {
            $execution = $this->executions->save($execution, $input->retryOfId);
        } catch (QueryException $e) {
            if ($input->idempotencyKey !== null && $this->isUniqueIdempotencyViolation($e)) {
                $existing = $this->executions->findByIdempotencyKey(
                    $input->workflowId,
                    $input->idempotencyKey,
                );

                if ($existing !== null) {
                    return $this->hydrate($existing, replayed: true);
                }
            }

            throw $e;
        }

        $execution = $this->markRunning($execution);

        $execution = $this->runSteps($execution, $input->triggerPayload);

        return $this->hydrate($execution, replayed: false);
    }

    /**
     * Runtime executes the SNAPSHOT definition, not live Workflow/WorkflowStep entities.
     *
     * @param  array<string, mixed>  $triggerPayload
     */
    private function runSteps(Execution $execution, array $triggerPayload): Execution
    {
        /** @var array<int, array<string, mixed>> $snapshotSteps */
        $snapshotSteps = $execution->workflowSnapshot['steps'] ?? [];

        /** @var array<int, array<string, mixed>> $stepOutputs */
        $stepOutputs = [];
        $failedPosition = null;

        foreach ($snapshotSteps as $stepDef) {
            $position = (int) $stepDef['position'];

            $step = $this->executions->saveStep(new ExecutionStep(
                id: null,
                executionId: (int) $execution->id,
                position: $position,
                stepSnapshot: $stepDef,
                status: ExecutionStepStatus::Pending,
                input: null,
                output: null,
                errorMessage: null,
                startedAt: null,
                finishedAt: null,
                createdAt: new DateTimeImmutable,
                updatedAt: new DateTimeImmutable,
            ));

            $step = $this->markStepRunning($step);

            $context = new ExecutionContext(
                triggerPayload: $triggerPayload,
                stepOutputs: $stepOutputs,
            );

            try {
                $resolved = $this->templates->resolve($stepDef['config'] ?? [], $context);
            } catch (TemplateResolutionFailed $e) {
                $this->markStepFailed($step, $e->getMessage());
                $failedPosition = $position;
                break;
            }

            $connectionId = isset($stepDef['connection_id']) && $stepDef['connection_id'] !== null
                ? (int) $stepDef['connection_id']
                : null;

            try {
                $output = $this->invoker->invoke(
                    (string) $stepDef['integration_key'],
                    (string) $stepDef['action_key'],
                    $execution->userId,
                    $connectionId,
                    is_array($resolved) ? $resolved : [],
                );
            } catch (ActionInvocationFailed|ConnectionNotFoundException|GoogleCredentialsUnavailableException|\Google\Service\Exception $e) {
                $this->markStepFailed($step, $e->getMessage());
                $failedPosition = $position;
                break;
            }

            $stepOutputs[$position] = $output;
            $this->markStepCompleted($step, $output);
        }

        if ($failedPosition !== null) {
            $this->skipRemainingSteps($execution, $snapshotSteps, $failedPosition);

            $errorMessage = $this->findStepError($execution, $failedPosition);

            return $this->markExecutionFailed($execution, $errorMessage ?? 'Step failed.');
        }

        return $this->markExecutionCompleted($execution);
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshotSteps
     */
    private function skipRemainingSteps(Execution $execution, array $snapshotSteps, int $failedPosition): void
    {
        $remaining = array_values(array_filter(
            $snapshotSteps,
            static fn (array $s) => (int) $s['position'] > $failedPosition,
        ));

        if (empty($remaining)) {
            return;
        }

        DB::transaction(function () use ($execution, $remaining) {
            foreach ($remaining as $stepDef) {
                $this->executions->saveStep(new ExecutionStep(
                    id: null,
                    executionId: (int) $execution->id,
                    position: (int) $stepDef['position'],
                    stepSnapshot: $stepDef,
                    status: ExecutionStepStatus::Skipped,
                    input: null,
                    output: null,
                    errorMessage: null,
                    startedAt: null,
                    finishedAt: null,
                    createdAt: new DateTimeImmutable,
                    updatedAt: new DateTimeImmutable,
                ));
            }
        });
    }

    private function findStepError(Execution $execution, int $position): ?string
    {
        foreach ($this->executions->listStepsForExecution((int) $execution->id) as $step) {
            if ($step->position === $position) {
                return $step->errorMessage;
            }
        }

        return null;
    }

    private function markRunning(Execution $execution): Execution
    {
        return $this->executions->save(new Execution(
            id: $execution->id,
            workflowId: $execution->workflowId,
            userId: $execution->userId,
            status: ExecutionStatus::Running,
            triggerSource: $execution->triggerSource,
            triggerPayload: $execution->triggerPayload,
            workflowSnapshot: $execution->workflowSnapshot,
            idempotencyKey: $execution->idempotencyKey,
            errorMessage: null,
            startedAt: new DateTimeImmutable,
            finishedAt: null,
            createdAt: $execution->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    private function markStepRunning(ExecutionStep $step): ExecutionStep
    {
        return $this->executions->saveStep(new ExecutionStep(
            id: $step->id,
            executionId: $step->executionId,
            position: $step->position,
            stepSnapshot: $step->stepSnapshot,
            status: ExecutionStepStatus::Running,
            input: null,
            output: null,
            errorMessage: null,
            startedAt: new DateTimeImmutable,
            finishedAt: null,
            createdAt: $step->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function markStepCompleted(ExecutionStep $step, array $output): ExecutionStep
    {
        return $this->executions->saveStep(new ExecutionStep(
            id: $step->id,
            executionId: $step->executionId,
            position: $step->position,
            stepSnapshot: $step->stepSnapshot,
            status: ExecutionStepStatus::Completed,
            input: null,
            output: $output,
            errorMessage: null,
            startedAt: $step->startedAt,
            finishedAt: new DateTimeImmutable,
            createdAt: $step->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    private function markStepFailed(ExecutionStep $step, string $errorMessage): ExecutionStep
    {
        return $this->executions->saveStep(new ExecutionStep(
            id: $step->id,
            executionId: $step->executionId,
            position: $step->position,
            stepSnapshot: $step->stepSnapshot,
            status: ExecutionStepStatus::Failed,
            input: null,
            output: null,
            errorMessage: $this->truncate($errorMessage),
            startedAt: $step->startedAt,
            finishedAt: new DateTimeImmutable,
            createdAt: $step->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    private function markExecutionCompleted(Execution $execution): Execution
    {
        return $this->executions->save(new Execution(
            id: $execution->id,
            workflowId: $execution->workflowId,
            userId: $execution->userId,
            status: ExecutionStatus::Completed,
            triggerSource: $execution->triggerSource,
            triggerPayload: $execution->triggerPayload,
            workflowSnapshot: $execution->workflowSnapshot,
            idempotencyKey: $execution->idempotencyKey,
            errorMessage: null,
            startedAt: $execution->startedAt,
            finishedAt: new DateTimeImmutable,
            createdAt: $execution->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    private function markExecutionFailed(Execution $execution, string $errorMessage): Execution
    {
        return $this->executions->save(new Execution(
            id: $execution->id,
            workflowId: $execution->workflowId,
            userId: $execution->userId,
            status: ExecutionStatus::Failed,
            triggerSource: $execution->triggerSource,
            triggerPayload: $execution->triggerPayload,
            workflowSnapshot: $execution->workflowSnapshot,
            idempotencyKey: $execution->idempotencyKey,
            errorMessage: $this->truncate($errorMessage),
            startedAt: $execution->startedAt,
            finishedAt: new DateTimeImmutable,
            createdAt: $execution->createdAt,
            updatedAt: new DateTimeImmutable,
        ));
    }

    private function hydrate(Execution $execution, bool $replayed): RunWorkflowResult
    {
        $stepEntities = $this->executions->listStepsForExecution((int) $execution->id);

        $steps = array_map(
            static fn (ExecutionStep $s) => ExecutionStepData::fromEntity($s),
            $stepEntities,
        );

        return new RunWorkflowResult(
            execution: ExecutionData::fromEntity($execution, $steps),
            replayed: $replayed,
        );
    }

    private function truncate(string $message): string
    {
        return strlen($message) > self::MAX_ERROR_LENGTH
            ? substr($message, 0, self::MAX_ERROR_LENGTH)
            : $message;
    }

    private function isUniqueIdempotencyViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return $sqlState === '23000';
    }
}
