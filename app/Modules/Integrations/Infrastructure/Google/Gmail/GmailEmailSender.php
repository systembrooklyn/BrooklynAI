<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Draft as GoogleGmailDraft;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use Google\Service\Gmail\Thread as GoogleGmailThread;

class GmailEmailSender
{
    public function send(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildMessage($fromEmail, $to, $subject, $htmlBody);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    /**
     * Send a reply within an existing Gmail thread.
     *
     * Setting `threadId` on the outgoing Message groups it into the thread in
     * the SENDER'S Gmail account, but it does NOT propagate to the RECIPIENT's
     * mail client. Recipients thread on the RFC 5322 headers `In-Reply-To:`
     * and `References:`, not on Gmail's internal thread ID. Without those
     * headers, the reply appears as a fresh email in any non-Gmail client and
     * in Gmail's own view when the recipient's Gmail account is different
     * from the sender's.
     *
     * To make the reply thread correctly for recipients, this method:
     *   1. Reads the last message of $threadId to extract its Message-ID and
     *      References headers.
     *   2. Builds the outgoing MIME with In-Reply-To: <parent-message-id> and
     *      References: <parent-references> <parent-message-id>.
     *   3. Still sets `threadId` so the sender's own view groups it.
     *
     * Graceful fallback: if the parent has no Message-ID (very unusual), the
     * code sends without In-Reply-To/References. That is exactly today's
     * behavior — sender sees the thread, recipient sees a new email. The fix
     * only applies where it can improve the outcome.
     */
    public function reply(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
        string $threadId,
    ): ?string {
        $service = $this->createService($credentials);

        $parentHeaders = $this->fetchThreadHeaders($service, $threadId);

        $message = $this->buildMessage(
            fromEmail: $fromEmail,
            to: $to,
            subject: $subject,
            htmlBody: $htmlBody,
            parentHeaders: $parentHeaders,
        );
        $message->setThreadId($threadId);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    /**
     * Create a draft without sending it.
     *
     * Requires the `gmail.compose` scope on the access token.
     *
     * @return array{draft_id: string, message_id: ?string}|null
     */
    public function createDraft(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): ?array {
        $service = $this->createServiceForScopes($credentials, [
            'https://www.googleapis.com/auth/gmail.compose',
        ]);

        $message = $this->buildMessage($fromEmail, $to, $subject, $htmlBody);

        $draft = new GoogleGmailDraft;
        $draft->setMessage($message);

        $created = $service->users_drafts->create('me', $draft);

        if (! $created instanceof GoogleGmailDraft) {
            return null;
        }

        $messageId = null;
        $draftMessage = $created->getMessage();
        if ($draftMessage !== null) {
            $messageId = $draftMessage->getId();
        }

        return [
            'draft_id' => (string) $created->getId(),
            'message_id' => $messageId,
        ];
    }

    /**
     * Send a plain-text email preserving the legacy calendar-invitation MIME format:
     * - From header includes display name: "Name <email>"
     * - Content-Type: text/plain; charset=UTF-8
     */
    public function sendPlainText(
        ResolvedGoogleCredentials $credentials,
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $body,
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildPlainTextMessage($fromName, $fromEmail, $to, $subject, $body);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        return $this->createServiceForScopes($credentials, [
            'https://www.googleapis.com/auth/gmail.send',
        ]);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    protected function createServiceForScopes(
        ResolvedGoogleCredentials $credentials,
        array $scopes,
    ): GoogleGmailService {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        foreach ($scopes as $scope) {
            $client->addScope($scope);
        }

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleGmailService($client);
    }

    /**
     * Fetch the RFC 5322 threading headers of the last message in a thread.
     *
     * The last message in the thread is the correct parent for a new reply.
     * This is what Gmail's own UI does — "Reply" always replies to the newest
     * message in the conversation.
     *
     * Returns nulls when the message has no Message-ID header (rare) or when
     * the thread has no messages (should not happen; the caller already holds
     * a valid threadId). Both cases are safe — the reply will be sent without
     * In-Reply-To/References and fall back to today's behavior.
     *
     * Overridable by tests. Never called outside `reply()`.
     *
     * @return array{message_id: ?string, references: ?string}
     */
    protected function fetchThreadHeaders(
        GoogleGmailService $service,
        string $threadId,
    ): array {
        try {
            /** @var GoogleGmailThread $thread */
            $thread = $service->users_threads->get('me', $threadId, [
                'format' => 'metadata',
                'metadataHeaders' => ['Message-ID', 'References', 'In-Reply-To'],
            ]);
        } catch (\Throwable) {
            // Reading the thread failed for any reason. Do not fail the send.
            // Fall back to today's behavior — threadId-only grouping.
            return ['message_id' => null, 'references' => null];
        }

        $messages = $thread->getMessages() ?? [];
        if (empty($messages)) {
            return ['message_id' => null, 'references' => null];
        }

        $parent = $messages[count($messages) - 1];
        $payload = $parent->getPayload();
        if ($payload === null) {
            return ['message_id' => null, 'references' => null];
        }

        $parentMessageId = null;
        $parentReferences = null;

        /** @var GoogleGmailMessagePartHeader $header */
        foreach ($payload->getHeaders() ?? [] as $header) {
            $name = strtolower((string) $header->getName());
            $value = (string) $header->getValue();

            if ($name === 'message-id') {
                $parentMessageId = trim($value);
            } elseif ($name === 'references') {
                $parentReferences = trim($value);
            }
        }

        if ($parentMessageId === null || $parentMessageId === '') {
            return ['message_id' => null, 'references' => null];
        }

        return [
            'message_id' => $parentMessageId,
            'references' => $parentReferences !== null && $parentReferences !== ''
                ? $parentReferences
                : null,
        ];
    }

    /**
     * @param  array{message_id: ?string, references: ?string}|null  $parentHeaders
     */
    protected function buildMessage(
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
        ?array $parentHeaders = null,
    ): GoogleGmailMessage {
        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";

        // RFC 5322 threading headers. Present only for replies where the
        // parent's Message-ID was successfully resolved.
        if (
            $parentHeaders !== null
            && isset($parentHeaders['message_id'])
            && is_string($parentHeaders['message_id'])
            && $parentHeaders['message_id'] !== ''
        ) {
            $parentMessageId = $parentHeaders['message_id'];

            $rawMessage .= "In-Reply-To: {$parentMessageId}\r\n";

            $references = isset($parentHeaders['references']) && is_string($parentHeaders['references'])
                ? trim($parentHeaders['references'])
                : '';

            $fullReferences = $references !== ''
                ? $references.' '.$parentMessageId
                : $parentMessageId;

            $rawMessage .= "References: {$fullReferences}\r\n";
        }

        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $rawMessage .= quoted_printable_encode($htmlBody);

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }

    protected function buildPlainTextMessage(
        string $fromName,
        string $fromEmail,
        string $to,
        string $subject,
        string $body,
    ): GoogleGmailMessage {
        $sender = "{$fromName} <{$fromEmail}>";

        $headers = [
            'From' => $sender,
            'To' => $to,
            'Subject' => $subject,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];

        $headerLines = '';
        foreach ($headers as $key => $value) {
            $headerLines .= "{$key}: {$value}\r\n";
        }
        $headerLines .= "\r\n";

        $encodedBody = quoted_printable_encode($body);
        $rawMessage = $headerLines.$encodedBody;

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }

    protected function dispatch(
        GoogleGmailService $service,
        GoogleGmailMessage $message,
    ): ?GoogleGmailMessage {
        $result = $service->users_messages->send('me', $message);

        return $result instanceof GoogleGmailMessage ? $result : null;
    }

    /**
     * Send an HTML email with an optional PDF attachment.
     * Preserves the legacy calendar-multipart MIME format used by
     * GoogleDocsController::generateAndEmailPdf.
     *
     * @param  array<int, string>  $toEmails
     */
    public function sendWithAttachment(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $htmlBody,
        ?string $pdfBinary = null,
        ?string $filename = 'document.pdf',
    ): ?string {
        $service = $this->createService($credentials);
        $message = $this->buildMessageWithAttachment($fromEmail, $toEmails, $subject, $htmlBody, $pdfBinary, $filename);

        $sent = $this->dispatch($service, $message);

        return $sent instanceof GoogleGmailMessage ? $sent->getId() : null;
    }

    /**
     * @param  array<int, string>  $toEmails
     */
    protected function buildMessageWithAttachment(
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $htmlBody,
        ?string $pdfBinary,
        ?string $filename,
    ): GoogleGmailMessage {
        $toHeader = implode(', ', $toEmails);

        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$toHeader}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";

        if ($pdfBinary) {
            $boundary = 'boundary_'.md5((string) time());
            $rawMessage .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody)."\r\n\r\n";

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($pdfBinary))."\r\n";
            $rawMessage .= "--{$boundary}--\r\n";
        } else {
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $rawMessage .= quoted_printable_encode($htmlBody);
        }

        $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

        $message = new GoogleGmailMessage;
        $message->setRaw($encoded);

        return $message;
    }
}
