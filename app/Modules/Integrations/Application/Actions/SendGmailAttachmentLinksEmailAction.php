<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\SendGmailAttachmentLinksEmailInput;
use App\Modules\Integrations\Application\DTOs\SendGmailEmailResult;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;

/**
 * Sends one HTML email containing clickable Drive links for the attachments
 * whose `web_view_link` is a usable non-empty string.
 *
 * The attachments array is expected to be the resolved value of the
 * `attachments` config field — typically the resolved `trigger.attachments`
 * array from the `new_email_received_with_attachment` trigger. This action
 * does not fetch bytes, does not manage Drive permissions, and does not
 * assume that a Drive link makes a file publicly accessible.
 *
 * The output shape matches the existing `send_email` action for consistency.
 */
final class SendGmailAttachmentLinksEmailAction
{
    private const FALLBACK_FILENAME = 'Attachment';

    private const FALLBACK_BODY = '<p>Attachments could not be linked.</p>';

    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailEmailSender $sender,
    ) {}

    public function execute(SendGmailAttachmentLinksEmailInput $input): SendGmailEmailResult
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $eligible = $this->selectEligibleAttachments($input->attachments);
        $html = $this->buildHtml($input->introText, $eligible);

        $messageId = $this->sender->send(
            credentials: $resolved,
            fromEmail: $input->fromEmail,
            to: $input->to,
            subject: $input->subject,
            htmlBody: $html,
        );

        return new SendGmailEmailResult(
            sent: true,
            messageId: $messageId,
        );
    }

    /**
     * Select only attachment entries whose `web_view_link` is a non-empty
     * string after trimming. Entries that are not arrays, or whose link is
     * missing, null, empty, whitespace-only, or not a string, are skipped.
     *
     * @param  array<int, mixed>  $attachments
     * @return array<int, array{filename: string, link: string}>
     */
    private function selectEligibleAttachments(array $attachments): array
    {
        $eligible = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $link = $attachment['web_view_link'] ?? null;

            if (! is_string($link)) {
                continue;
            }

            $link = trim($link);

            if ($link === '') {
                continue;
            }

            $eligible[] = [
                'filename' => $this->resolveFilename($attachment),
                'link' => $link,
            ];
        }

        return $eligible;
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function resolveFilename(array $attachment): string
    {
        $filename = $attachment['filename'] ?? null;

        if (is_string($filename) && trim($filename) !== '') {
            return trim($filename);
        }

        return self::FALLBACK_FILENAME;
    }

    /**
     * @param  array<int, array{filename: string, link: string}>  $eligible
     */
    private function buildHtml(?string $intro, array $eligible): string
    {
        $parts = [];

        if ($intro !== null && trim($intro) !== '') {
            $parts[] = '<p>'.$this->escape($intro).'</p>';
        }

        if ($eligible === []) {
            $parts[] = self::FALLBACK_BODY;

            return implode("\n", $parts);
        }

        $items = [];

        foreach ($eligible as $entry) {
            $href = $this->escape($entry['link']);
            $label = $this->escape($entry['filename']);
            $items[] = '<li><a href="'.$href.'">'.$label.'</a></li>';
        }

        $parts[] = "<ul>\n".implode("\n", $items)."\n</ul>";

        return implode("\n", $parts);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
