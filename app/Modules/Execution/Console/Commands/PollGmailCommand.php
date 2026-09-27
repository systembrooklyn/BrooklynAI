<?php

namespace App\Modules\Execution\Console\Commands;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesAction;
use App\Modules\Integrations\Application\DTOs\FetchGmailTriggerMessagesInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

final class PollGmailCommand extends Command
{
    private const INTEGRATION_KEY = 'google.gmail';

    private const TRIGGER_KEY = 'new_email_received';

    private const OVERLAP_SECONDS = 60;

    private const DEFAULT_INTERVAL_MINUTES = 1;

    private const LOCK_SECONDS = 90;

    private const DEFAULT_RATE_LIMIT = 10;

    private const RATE_LIMIT_DECAY_SECONDS = 60;

    protected $signature = 'workflows:poll-gmail';

    protected $description = 'Poll Gmail for new email triggers across active workflows.';

    public function handle(
        WorkflowRepository $workflows,
        ExecutionRepository $executions,
        FetchGmailTriggerMessagesAction $fetch,
        ConnectionRepository $connections,
    ): int {
        $now = new DateTimeImmutable;

        $discovered = $workflows->listActiveWithTriggerDue(
            self::INTEGRATION_KEY,
            self::TRIGGER_KEY,
            $now,
        );

        $processed = 0;
        $executed = 0;
        $skipped = 0;

        foreach ($discovered as $pair) {
            $workflow = $pair['workflow'];
            $trigger = $pair['trigger'];
            $processed++;

            $lock = Cache::lock('poll-trigger:'.$trigger->id, self::LOCK_SECONDS);
            if (! $lock->get()) {
                $skipped++;
                $this->line(sprintf('Workflow %d: skipped (locked by another poller)', $workflow->id));

                continue;
            }

            try {
                $outcome = $this->processWorkflow(
                    $workflow, $trigger, $executions, $fetch, $connections, $workflows, $now,
                );

                if ($outcome === 'executed') {
                    $executed++;
                } else {
                    $skipped++;
                }
            } catch (GmailProviderException $e) {
                if ($e->retryable) {
                    $skipped++;
                    $this->warn(sprintf('Workflow %d: Gmail transient failure (%s)', $workflow->id, $e->getMessage()));

                    continue;
                }
                throw $e;
            } catch (GoogleCredentialsUnavailableException|ConnectionNotFoundException $e) {
                $skipped++;
                $this->warn(sprintf('Workflow %d: %s', $workflow->id, $e->getMessage()));
            } finally {
                $lock->release();
            }
        }

        $this->info(sprintf('Processed %d. Executed %d. Skipped %d.', $processed, $executed, $skipped));

        return self::SUCCESS;
    }

    private function processWorkflow(
        Workflow $workflow,
        WorkflowTrigger $trigger,
        ExecutionRepository $executions,
        FetchGmailTriggerMessagesAction $fetch,
        ConnectionRepository $connections,
        WorkflowRepository $workflows,
        DateTimeImmutable $now,
    ): string {
        if ($executions->hasInProgressForWorkflow((int) $workflow->id)) {
            $this->line(sprintf('Workflow %d: skipped (execution in progress)', $workflow->id));

            return 'skipped';
        }

        if ($trigger->pollCursor === null) {
            $initialized = $this->withState(
                $trigger,
                cursor: $now->getTimestamp(),
                nextPollAt: $this->nextPollAtFrom($now, $trigger->intervalMinutes),
            );
            $workflows->saveTrigger($initialized);
            $this->line(sprintf('Workflow %d: initialized cursor', $workflow->id));

            return 'skipped';
        }

        $labelIds = $this->extractLabelIds($trigger);
        if ($labelIds === null) {
            $this->warn(sprintf('Workflow %d: invalid label_id, skipping', $workflow->id));

            return 'skipped';
        }

        $after = max($trigger->pollCursor - self::OVERLAP_SECONDS, 0);

        $fetched = $fetch->execute(new FetchGmailTriggerMessagesInput(
            userId: $workflow->userId,
            connectionId: $trigger->connectionId,
            afterEpochSeconds: $after,
            labelIds: $labelIds,
        ));

        $maxSeenSeconds = $this->maxSeenSeconds($fetched);

        $selfEmail = $this->resolveConnectionEmail($connections, $workflow->userId, $trigger->connectionId);
        $payloads = $selfEmail !== null
            ? $this->filterOutSelfEmails($fetched, $selfEmail, $workflow)
            : $fetched;

        if (empty($payloads)) {
            $updated = $this->withState(
                $trigger,
                cursor: $maxSeenSeconds !== null ? $maxSeenSeconds + 1 : $trigger->pollCursor,
                nextPollAt: $this->nextPollAtFrom($now, $trigger->intervalMinutes),
            );
            $workflows->saveTrigger($updated);
            $this->line(sprintf('Workflow %d: no new messages', $workflow->id));

            return 'skipped';
        }

        usort($payloads, function (array $a, array $b): int {
            $c = ((int) $a['received_at_epoch_ms']) <=> ((int) $b['received_at_epoch_ms']);

            return $c !== 0 ? $c : strcmp((string) $a['message_id'], (string) $b['message_id']);
        });

        $rateKey = 'workflow-exec:'.$workflow->id;
        $rateLimit = (int) config('automation.execution_rate_limit_per_minute', self::DEFAULT_RATE_LIMIT);

        $dispatched = 0;

        foreach ($payloads as $payload) {
            $messageId = (string) $payload['message_id'];
            $idempotencyKey = 'gmail:'.$workflow->id.':'.$messageId;

            if ($executions->findByIdempotencyKey((int) $workflow->id, $idempotencyKey) !== null) {
                continue;
            }

            if (RateLimiter::tooManyAttempts($rateKey, $rateLimit)) {
                $availableIn = RateLimiter::availableIn($rateKey);
                RunWorkflowJob::dispatch(
                    userId: $workflow->userId,
                    workflowId: (int) $workflow->id,
                    triggerPayload: $payload,
                    idempotencyKey: $idempotencyKey,
                    triggerSource: 'poll',
                )->delay(now()->addSeconds($availableIn + 1));
            } else {
                RateLimiter::hit($rateKey, self::RATE_LIMIT_DECAY_SECONDS);
                RunWorkflowJob::dispatch(
                    userId: $workflow->userId,
                    workflowId: (int) $workflow->id,
                    triggerPayload: $payload,
                    idempotencyKey: $idempotencyKey,
                    triggerSource: 'poll',
                );
            }

            $dispatched++;
        }

        $cursor = $maxSeenSeconds !== null ? $maxSeenSeconds + 1 : $trigger->pollCursor;
        $updated = $this->withState(
            $trigger,
            cursor: $cursor,
            nextPollAt: $this->nextPollAtFrom($now, $trigger->intervalMinutes),
        );
        $workflows->saveTrigger($updated);

        return $dispatched > 0 ? 'executed' : 'skipped';
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

    private function resolveConnectionEmail(
        ConnectionRepository $connections,
        int $userId,
        ?int $connectionId,
    ): ?string {
        if ($connectionId === null) {
            return null;
        }

        return $connections->findForUser($userId, $connectionId)?->email;
    }

    private function nextPollAtFrom(DateTimeImmutable $now, ?int $intervalMinutes): DateTimeImmutable
    {
        $interval = $intervalMinutes ?? self::DEFAULT_INTERVAL_MINUTES;
        if ($interval < 1) {
            $interval = self::DEFAULT_INTERVAL_MINUTES;
        }

        return $now->modify('+'.$interval.' minutes');
    }

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

    private function withState(
        WorkflowTrigger $trigger,
        int $cursor,
        DateTimeImmutable $nextPollAt,
    ): WorkflowTrigger {
        return new WorkflowTrigger(
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
            nextPollAt: $nextPollAt,
        );
    }
}
