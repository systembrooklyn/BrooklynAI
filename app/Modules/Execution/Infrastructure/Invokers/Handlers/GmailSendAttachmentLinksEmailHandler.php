<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\SendGmailAttachmentLinksEmailAction;
use App\Modules\Integrations\Application\DTOs\SendGmailAttachmentLinksEmailInput;

/**
 * Thin adapter for the send_attachment_links_email action.
 *
 * Responsibilities (and ONLY these):
 *   1. Validate the resolved config against the action's declared fields.
 *   2. Build the SendGmailAttachmentLinksEmailInput DTO.
 *   3. Delegate to SendGmailAttachmentLinksEmailAction.
 *   4. Whitelist the returned output into a plain array.
 *
 * The `attachments` field arrives here already resolved by the template
 * resolver. When the client submits the standard template value
 * `{{ trigger.attachments }}`, the resolver returns the raw PHP array from
 * the trigger payload (see RegexTemplateResolver's whole-string path).
 */
final class GmailSendAttachmentLinksEmailHandler implements ActionHandler
{
    public function __construct(
        private readonly SendGmailAttachmentLinksEmailAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $to = $resolvedConfig['to'] ?? null;
        $subject = $resolvedConfig['subject'] ?? null;
        $attachments = $resolvedConfig['attachments'] ?? null;
        $introText = $resolvedConfig['intro_text'] ?? null;

        if (! is_string($to) || $to === '') {
            throw ActionInvocationFailed::forInvalidConfig('to');
        }

        if (! is_string($subject) || $subject === '') {
            throw ActionInvocationFailed::forInvalidConfig('subject');
        }

        if (! is_array($attachments)) {
            throw ActionInvocationFailed::forInvalidConfig('attachments');
        }

        if ($introText !== null && ! is_string($introText)) {
            throw ActionInvocationFailed::forInvalidConfig('intro_text');
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw ActionInvocationFailed::forMissingUser($userId);
        }

        // Known limitation, shared with GmailSendEmailHandler and
        // GmailReplyToEmailHandler: `fromEmail` is always the platform user's
        // email, even when an explicit connection is supplied. Gmail delivers
        // the message as authored by the authenticated connection regardless
        // of the From: header value.
        $result = $this->action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: $userId,
            fromEmail: (string) $user->email,
            to: $to,
            subject: $subject,
            introText: $introText,
            attachments: $attachments,
            connectionId: $connectionId,
        ));

        return [
            'sent' => $result->sent,
            'message_id' => $result->messageId,
        ];
    }
}
