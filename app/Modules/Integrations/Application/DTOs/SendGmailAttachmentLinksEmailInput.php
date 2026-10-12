<?php

namespace App\Modules\Integrations\Application\DTOs;

/**
 * Input for the send_attachment_links_email action.
 *
 * The attachments array is expected to be the resolved value of the
 * `attachments` config field — i.e. whatever the client stored there,
 * resolved by the existing template resolver against the trigger payload.
 * In the normal case it is the resolved `trigger.attachments` array.
 *
 * @phpstan-type AttachmentEntry array<string, mixed>
 */
final class SendGmailAttachmentLinksEmailInput
{
    /**
     * @param  array<int, AttachmentEntry>  $attachments
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $fromEmail,
        public readonly string $to,
        public readonly string $subject,
        public readonly ?string $introText,
        public readonly array $attachments,
        public readonly ?int $connectionId = null,
    ) {}
}
