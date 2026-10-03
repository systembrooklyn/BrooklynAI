<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\CreateGmailDraftAction;
use App\Modules\Integrations\Application\DTOs\CreateGmailDraftInput;

final class GmailCreateDraftHandler implements ActionHandler
{
    public function __construct(
        private readonly CreateGmailDraftAction $action,
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

        // Known limitation (shared with GmailSendEmailHandler and
        // GmailReplyToEmailHandler): `fromEmail` is the platform user's email.
        // Gmail delivers the draft as authored by the authenticated connection.
        $result = $this->action->execute(new CreateGmailDraftInput(
            userId: $userId,
            fromEmail: (string) $user->email,
            to: $to,
            subject: $subject,
            htmlBody: $body,
            connectionId: $connectionId,
        ));

        return [
            'created' => $result->created,
            'draft_id' => $result->draftId,
            'message_id' => $result->messageId,
        ];
    }
}
