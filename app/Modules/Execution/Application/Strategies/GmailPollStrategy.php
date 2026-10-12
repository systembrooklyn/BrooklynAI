<?php

namespace App\Modules\Execution\Application\Strategies;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\Contracts\TriggerStrategy;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Application\DTOs\StrategyResult;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesAction;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesWithAttachmentsAction;
use App\Modules\Integrations\Application\DTOs\FetchGmailTriggerMessagesInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailTriggerQueryComposer;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The single Gmail polling strategy.
 *
 * Two trigger keys share this strategy:
 *
 *   new_email_received
 *     → existing lightweight path. 14-key payload produced by
 *       GmailMessagePayloadBuilder::build(). No attachment work.
 *
 *   new_email_received_with_attachment
 *     → attachment-aware path. Same cursor, same idempotency key
 *       format, same self-email filter, same ordering. Additionally
 *       ensures has:attachment is present in the Gmail query and
 *       dispatches to the attachment-aware fetch action, which
 *       materializes attachments into the user's Drive and adds the
 *       `attachments` key to the payload.
 *
 * There is exactly one polling mechanism. There is no second cursor,
 * no second scheduler, no second idempotency mechanism.
 */
final class GmailPollStrategy implements TriggerStrategy
{
    private const STRATEGY_KEY = 'poll';

    private const INTEGRATION_KEY = 'google.gmail';

    private const TRIGGER_KEY = 'new_email_received';

    private const TRIGGER_KEY_WITH_ATTACHMENT = 'new_email_received_with_attachment';

    private const DRIVE_FILE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const HAS_ATTACHMENT_QUERY_FRAGMENT = 'has:attachment';

    // Overlap window covers the full external tick interval (5 minutes) plus
    // a 1-minute safety margin. A shorter window would lose messages that were
    // fetched but not executed if the tick process is terminated mid-run.
    // Duplicate fetches are idempotent via the `gmail:{workflowId}:{messageId}`
    // idempotency key, so the cost is only extra Gmail API calls, never
    // duplicate executions.
    private const OVERLAP_SECONDS = 360;

    private const DEFAULT_INTERVAL_MINUTES = 5;

    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ConnectionRepository $connections,
        private readonly ExecutionRepository $executions,
        private readonly FetchGmailTriggerMessagesAction $fetch,
        private readonly FetchGmailTriggerMessagesWithAttachmentsAction $fetchWithAttachments,
        private readonly RunWorkflowAction $runWorkflow,
        private readonly GmailTriggerQueryComposer $queryComposer,
    ) {}

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function process(
        Workflow $workflow,
        WorkflowTrigger $trigger,
        DateTimeImmutable $now,
    ): StrategyResult {
        if ($workflow->id === null) {
            return StrategyResult::skipped('missing_workflow_id');
        }

        if (
            $trigger->integrationKey !== self::INTEGRATION_KEY
            || ! in_array(
                $trigger->triggerKey,
                [self::TRIGGER_KEY, self::TRIGGER_KEY_WITH_ATTACHMENT],
                true,
            )
        ) {
            return StrategyResult::skipped('unsupported_trigger');
        }

        $isAttachmentTrigger = $trigger->triggerKey === self::TRIGGER_KEY_WITH_ATTACHMENT;

        // The attachment-aware trigger requires a Drive-capable connection.
        // This check happens before any cursor or execution work so that a
        // missing scope does not advance the cursor — the next tick will
        // retry once the user connects Drive.
        if (
            $isAttachmentTrigger
            && ! $this->connectionHasDriveScope($workflow->userId, $trigger->connectionId)
        ) {
            return StrategyResult::skipped('drive_connection_required');
        }

        if ($this->executions->hasInProgressForWorkflow((int) $workflow->id)) {
            return StrategyResult::skipped('execution_in_progress');
        }

        if ($trigger->pollCursor === null) {
            $this->advanceState($trigger, $now, null);

            return StrategyResult::skipped('cursor_initialized');
        }

        $labelIds = $this->extractLabelIds($trigger);
        if ($labelIds === null) {
            return StrategyResult::skipped('malformed_label_id');
        }

        $query = $this->queryComposer->compose($trigger->config);

        if ($isAttachmentTrigger) {
            $query = $this->withHasAttachmentFilter($query);
        }

        $after = max($trigger->pollCursor - self::OVERLAP_SECONDS, 0);

        $input = new FetchGmailTriggerMessagesInput(
            userId: $workflow->userId,
            connectionId: $trigger->connectionId,
            afterEpochSeconds: $after,
            labelIds: $labelIds,
            query: $query,
        );

        try {
            $fetched = $isAttachmentTrigger
                ? $this->fetchWithAttachments->execute($input)
                : $this->fetch->execute($input);
        } catch (GmailProviderException $e) {
            if ($e->retryable) {
                return StrategyResult::skipped('gmail_transient_failure');
            }

            throw $e;
        } catch (GoogleCredentialsUnavailableException | ConnectionNotFoundException) {
            return StrategyResult::skipped('credentials_unavailable');
        }

        $maxSeenSeconds = $this->maxSeenSeconds($fetched);

        $selfEmail = $this->resolveConnectionEmail($workflow->userId, $trigger->connectionId);
        $payloads = $selfEmail !== null
            ? $this->filterOutSelfEmails($fetched, $selfEmail, $workflow)
            : $fetched;

        if (empty($payloads)) {
            $this->advanceState($trigger, $now, $maxSeenSeconds);

            return StrategyResult::skipped('no_new_messages');
        }

        usort($payloads, function (array $a, array $b): int {
            $c = ((int) $a['received_at_epoch_ms']) <=> ((int) $b['received_at_epoch_ms']);

            return $c !== 0 ? $c : strcmp((string) $a['message_id'], (string) $b['message_id']);
        });

        $executed = 0;

        foreach ($payloads as $payload) {
            $messageId = (string) $payload['message_id'];
            $idempotencyKey = 'gmail:' . $workflow->id . ':' . $messageId;

            if ($this->executions->findByIdempotencyKey((int) $workflow->id, $idempotencyKey) !== null) {
                continue;
            }

            try {
                $this->runWorkflow->execute(new RunWorkflowInput(
                    userId: $workflow->userId,
                    workflowId: (int) $workflow->id,
                    triggerPayload: $payload,
                    idempotencyKey: $idempotencyKey,
                    triggerSource: 'poll',
                ));
                $executed++;
            } catch (WorkflowNotFoundException | WorkflowNotExecutableException) {
                continue;
            } catch (ExecutionAlreadyRunningException) {
                break;
            }
        }

        $this->advanceState($trigger, $now, $maxSeenSeconds);

        return StrategyResult::executed($executed);
    }

    private function connectionHasDriveScope(int $userId, ?int $connectionId): bool
    {
        if ($connectionId === null) {
            return false;
        }

        $connection = $this->connections->findForUser($userId, $connectionId);

        return $connection !== null && $connection->hasScope(self::DRIVE_FILE_SCOPE);
    }

    private function withHasAttachmentFilter(?string $query): string
    {
        if ($query === null || trim($query) === '') {
            return self::HAS_ATTACHMENT_QUERY_FRAGMENT;
        }

        if (str_contains($query, self::HAS_ATTACHMENT_QUERY_FRAGMENT)) {
            return $query;
        }

        return $query . ' ' . self::HAS_ATTACHMENT_QUERY_FRAGMENT;
    }

    private function advanceState(WorkflowTrigger $trigger, DateTimeImmutable $now, ?int $maxSeenSeconds): void
    {
        $interval = $trigger->intervalMinutes ?? self::DEFAULT_INTERVAL_MINUTES;
        if ($interval < 1) {
            $interval = self::DEFAULT_INTERVAL_MINUTES;
        }

        $cursor = $maxSeenSeconds !== null
            ? $maxSeenSeconds + 1
            : ($trigger->pollCursor ?? $now->getTimestamp());

        $updated = new WorkflowTrigger(
            id: $trigger->id,
            workflowId: $trigger->workflowId,
            integrationKey: $trigger->integrationKey,
            triggerKey: $trigger->triggerKey,
            connectionId: $trigger->connectionId,
            strategy: $trigger->strategy,
            config: $trigger->config,
            intervalMinutes: $trigger->intervalMinutes,
            createdAt: $trigger->createdAt,
            updatedAt: new DateTimeImmutable,
            pollCursor: $cursor,
            nextPollAt: $now->modify('+' . $interval . ' minutes'),
        );

        $this->workflows->saveTrigger($updated);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fetched
     */
    private function maxSeenSeconds(array $fetched): ?int
    {
        $max = null;
        foreach ($fetched as $item) {
            $seen = intdiv((int) $item['received_at_epoch_ms'], 1000);
            if ($max === null || $seen > $max) {
                $max = $seen;
            }
        }

        return $max;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    private function filterOutSelfEmails(array $payloads, string $selfEmail, Workflow $workflow): array
    {
        $kept = [];
        $selfLower = strtolower($selfEmail);

        foreach ($payloads as $payload) {
            $sender = $this->extractEmailAddress((string) ($payload['from'] ?? ''));
            if ($sender !== null && strcasecmp($sender, $selfLower) === 0) {
                Log::info('Gmail poll: skipped self-authored email', [
                    'workflow_id' => $workflow->id,
                    'message_id' => $payload['message_id'] ?? null,
                ]);

                continue;
            }
            $kept[] = $payload;
        }

        return $kept;
    }

    private function extractEmailAddress(string $header): ?string
    {
        $header = trim($header);
        if ($header === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $header, $m) === 1) {
            $email = strtolower(trim($m[1]));

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
        }

        $email = strtolower($header);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function resolveConnectionEmail(int $userId, ?int $connectionId): ?string
    {
        if ($connectionId === null) {
            return null;
        }

        return $this->connections->findForUser($userId, $connectionId)?->email;
    }

    /**
     * @return array<int, string>|null
     */
    private function extractLabelIds(WorkflowTrigger $trigger): ?array
    {
        $config = $trigger->config;

        if (! array_key_exists('label_id', $config)) {
            return [];
        }

        $value = $config['label_id'];

        if (! is_string($value) || $value === '') {
            return null;
        }

        return [$value];
    }
}
