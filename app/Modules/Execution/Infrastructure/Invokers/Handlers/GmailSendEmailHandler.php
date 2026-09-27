<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\SendGmailEmailAction;
use App\Modules\Integrations\Application\DTOs\SendGmailEmailInput;

final class GmailSendEmailHandler implements ActionHandler
{
    public function __construct(
        private readonly SendGmailEmailAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $to = $resolvedConfig['to'] ?? null;
        $subject = $resolvedConfig['subject'] ?? null;
        $body = $resolvedConfig['body'] ?? null;

        if (! is_string($to) || $to === '') {
            throw ActionInvocationFailed::forInvalidConfig('to');
        }

        if (! is_string($subject) || $subject === '') {
            throw ActionInvocationFailed::forInvalidConfig('subject');
        }

        if (! is_string($body)) {
            throw ActionInvocationFailed::forInvalidConfig('body');
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw ActionInvocationFailed::forMissingUser($userId);
        }

        // Known Phase 6 limitation:
        // `fromEmail` is always the platform user's email, even when an
        // explicit connection is supplied. This matches Phase 5 behavior
        // of SendGmailEmailAction. A future dedicated batch may align
        // `fromEmail` with the connection's own email address. It is NOT
        // a credential-resolution shortcut and does NOT fall back to
        // legacy `users.google_*` tokens.
        $result = $this->action->execute(new SendGmailEmailInput(
            userId: $userId,
            fromEmail: (string) $user->email,
            to: $to,
            subject: $subject,
            htmlBody: $body,
            connectionId: $connectionId,
        ));

        return [
            'sent' => $result->sent,
            'message_id' => $result->messageId,
        ];
    }
}
