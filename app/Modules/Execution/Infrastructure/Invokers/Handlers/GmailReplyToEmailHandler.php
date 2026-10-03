<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\ReplyToGmailEmailAction;
use App\Modules\Integrations\Application\DTOs\ReplyToGmailEmailInput;

final class GmailReplyToEmailHandler implements ActionHandler
{
    public function __construct(
        private readonly ReplyToGmailEmailAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $to = $resolvedConfig['to'] ?? null;
        $subject = $resolvedConfig['subject'] ?? null;
        $body = $resolvedConfig['body'] ?? null;
        $threadId = $resolvedConfig['thread_id'] ?? null;

        if (! is_string($to) || $to === '') {
            throw ActionInvocationFailed::forInvalidConfig('to');
        }

        if (! is_string($subject) || $subject === '') {
            throw ActionInvocationFailed::forInvalidConfig('subject');
        }

        if (! is_string($body)) {
            throw ActionInvocationFailed::forInvalidConfig('body');
        }

        if (! is_string($threadId) || $threadId === '') {
            throw ActionInvocationFailed::forInvalidConfig('thread_id');
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw ActionInvocationFailed::forMissingUser($userId);
        }

        // Known limitation (shared with GmailSendEmailHandler):
        // `fromEmail` is always the platform user's email. The Gmail API
        // delivers the reply from the authenticated connection account
        // regardless of the From: header value.
        $result = $this->action->execute(new ReplyToGmailEmailInput(
            userId: $userId,
            fromEmail: (string) $user->email,
            to: $to,
            subject: $subject,
            htmlBody: $body,
            threadId: $threadId,
            connectionId: $connectionId,
        ));

        return [
            'sent' => $result->sent,
            'message_id' => $result->messageId,
        ];
    }
}
