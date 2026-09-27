<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use DateTimeImmutable;
use DateTimeInterface;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;

final class GmailMessagePayloadBuilder
{
    /**
     * Build the canonical, whitelisted Gmail trigger payload.
     *
     * The output array has exactly the following keys and nothing else:
     *
     *   message_id
     *   thread_id
     *   from
     *   to
     *   cc
     *   subject
     *   snippet
     *   body
     *   received_at
     *   received_at_epoch_ms
     *   labels
     *   has_attachment
     *   attachment_count
     *   attachment_ids
     *
     * @return array<string, mixed>
     */
    public function build(GoogleGmailMessage $message): array
    {
        $payload = $message->getPayload();

        $headers = $this->extractHeaders($payload);

        $receivedAtMs = (int) ($message->getInternalDate() ?? 0);
        $receivedAt = (new DateTimeImmutable)
            ->setTimestamp(intdiv($receivedAtMs, 1000))
            ->format(DateTimeInterface::ATOM);

        $attachmentIds = $this->extractAttachmentIds($payload);
        $hasAttachment = count($attachmentIds) > 0;

        return [
            'message_id' => (string) $message->getId(),
            'thread_id' => (string) $message->getThreadId(),
            'from' => (string) ($headers['from'] ?? ''),
            'to' => $this->parseAddressList($headers['to'] ?? ''),
            'cc' => $this->parseAddressList($headers['cc'] ?? ''),
            'subject' => (string) ($headers['subject'] ?? ''),
            'snippet' => (string) ($message->getSnippet() ?? ''),
            'body' => $this->extractBody($payload),
            'received_at' => $receivedAt,
            'received_at_epoch_ms' => $receivedAtMs,
            'labels' => array_values($message->getLabelIds() ?? []),
            'has_attachment' => $hasAttachment,
            'attachment_count' => count($attachmentIds),
            'attachment_ids' => $attachmentIds,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractHeaders(?GoogleGmailMessagePart $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $headers = [];

        foreach ($payload->getHeaders() ?? [] as $header) {
            $name = strtolower((string) $header->getName());

            if ($name === '') {
                continue;
            }

            // First occurrence wins, matching Gmail's displayed headers.
            if (! array_key_exists($name, $headers)) {
                $headers[$name] = (string) $header->getValue();
            }
        }

        return $headers;
    }

    /**
     * Split a comma-separated address header into trimmed tokens.
     *
     * Does not attempt to parse display names or quoted values. The
     * raw tokens are preserved so no information is invented.
     *
     * @return array<int, string>
     */
    private function parseAddressList(string $value): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $trimmed) ?: [];
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, static fn (string $p) => $p !== '');

        return array_values($parts);
    }

    private function extractBody(?GoogleGmailMessagePart $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $plain = $this->collectPartsOfType($payload, 'text/plain');

        if (count($plain) > 0) {
            return trim(implode("\n", $plain));
        }

        $html = $this->collectPartsOfType($payload, 'text/html');

        return trim(implode("\n", $html));
    }

    /**
     * @return array<int, string>
     */
    private function collectPartsOfType(GoogleGmailMessagePart $part, string $mimeType): array
    {
        $out = [];

        if (strcasecmp((string) $part->getMimeType(), $mimeType) === 0) {
            $decoded = $this->decodeBodyData($part->getBody());

            if ($decoded !== '') {
                $out[] = $decoded;
            }
        }

        foreach ($part->getParts() ?? [] as $child) {
            $out = array_merge($out, $this->collectPartsOfType($child, $mimeType));
        }

        return $out;
    }

    private function decodeBodyData(?GoogleGmailMessagePartBody $body): string
    {
        if ($body === null) {
            return '';
        }

        $data = $body->getData();

        if (! is_string($data) || $data === '') {
            return '';
        }

        // Gmail returns base64url without padding.
        $normalized = strtr($data, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            return '';
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function extractAttachmentIds(?GoogleGmailMessagePart $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $ids = [];
        $this->collectAttachmentIds($payload, $ids);

        return $ids;
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function collectAttachmentIds(GoogleGmailMessagePart $part, array &$ids): void
    {
        $body = $part->getBody();
        $attachmentId = $body?->getAttachmentId();

        if (is_string($attachmentId) && $attachmentId !== '') {
            $ids[] = $attachmentId;
        }

        foreach ($part->getParts() ?? [] as $child) {
            $this->collectAttachmentIds($child, $ids);
        }
    }
}
