<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that GmailEmailSender::reply() produces a raw MIME body that
 * threads correctly on the RECIPIENT's side via In-Reply-To / References.
 *
 * The sender's threadId-based grouping is already correct in the sender's
 * own Gmail account — this test proves the additional RFC 5322 headers
 * that make other mail clients thread the reply correctly.
 *
 * Constructed by an anonymous subclass that overrides createService(),
 * fetchThreadHeaders(), and dispatch(). No real network call happens.
 */
final class GmailEmailSenderReplyHeadersTest extends TestCase
{
    /**
     * @param  array{message_id: ?string, references: ?string}  $parentHeaders
     * @param  array<int, GoogleGmailMessage>  $sentMessages  filled in by reference
     */
    private function makeSender(
        array $parentHeaders,
        array &$sentMessages,
    ): GmailEmailSender {
        return new class($parentHeaders, $sentMessages) extends GmailEmailSender
        {
            /** @var array{message_id: ?string, references: ?string} */
            private array $parentHeaders;

            /** @var array<int, GoogleGmailMessage> */
            private array $sent;

            public function __construct(array $parentHeaders, array &$sent)
            {
                $this->parentHeaders = $parentHeaders;
                // Bind by reference so writes propagate back to the test.
                $this->sent = &$sent;
            }

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
            {
                // A minimal GoogleGmailService; its methods are never invoked
                // because dispatch() and fetchThreadHeaders() are overridden.
                return new GoogleGmailService(new \Google\Client);
            }

            protected function fetchThreadHeaders(GoogleGmailService $service, string $threadId): array
            {
                return $this->parentHeaders;
            }

            protected function dispatch(
                GoogleGmailService $service,
                GoogleGmailMessage $message,
            ): ?GoogleGmailMessage {
                $this->sent[] = $message;

                // Return a synthetic sent-message so reply() returns an id.
                $sent = new GoogleGmailMessage;
                $sent->setId('sent-message-id');

                return $sent;
            }
        };
    }

    private function decodeRaw(GoogleGmailMessage $message): string
    {
        $raw = (string) $message->getRaw();

        // Gmail uses URL-safe base64 without padding.
        $base64 = strtr($raw, '-_', '+/');
        $decoded = base64_decode($base64, true);

        return $decoded === false ? '' : $decoded;
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            expiresAt: null,
        );
    }

    // ------------------------------------------------------------------
    // Positive cases
    // ------------------------------------------------------------------

    public function test_reply_includes_in_reply_to_and_references_when_parent_has_both(): void
    {
        /** @var array<int, GoogleGmailMessage> $sent */
        $sent = [];

        $sender = $this->makeSender(
            parentHeaders: [
                'message_id' => '<parent@example.com>',
                'references' => '<grandparent@example.com>',
            ],
            sentMessages: $sent,
        );

        $result = $sender->reply(
            credentials: $this->credentials(),
            fromEmail: 'from@example.com',
            to: 'recipient@example.com',
            subject: 'Re: Original',
            htmlBody: '<p>Reply</p>',
            threadId: 'thread-123',
        );

        $this->assertSame('sent-message-id', $result);
        $this->assertCount(1, $sent);

        $raw = $this->decodeRaw($sent[0]);

        $this->assertStringContainsString("\r\nIn-Reply-To: <parent@example.com>\r\n", $raw);
        $this->assertStringContainsString(
            "\r\nReferences: <grandparent@example.com> <parent@example.com>\r\n",
            $raw,
        );
        $this->assertSame('thread-123', $sent[0]->getThreadId());
    }

    public function test_reply_includes_references_as_single_value_when_parent_has_no_prior_references(): void
    {
        /** @var array<int, GoogleGmailMessage> $sent */
        $sent = [];

        $sender = $this->makeSender(
            parentHeaders: [
                'message_id' => '<parent@example.com>',
                'references' => null,
            ],
            sentMessages: $sent,
        );

        $sender->reply(
            credentials: $this->credentials(),
            fromEmail: 'from@example.com',
            to: 'recipient@example.com',
            subject: 'Re: Original',
            htmlBody: '<p>Reply</p>',
            threadId: 'thread-123',
        );

        $raw = $this->decodeRaw($sent[0]);

        $this->assertStringContainsString("\r\nIn-Reply-To: <parent@example.com>\r\n", $raw);
        $this->assertStringContainsString("\r\nReferences: <parent@example.com>\r\n", $raw);
    }

    // ------------------------------------------------------------------
    // Graceful fallback
    // ------------------------------------------------------------------

    public function test_reply_falls_back_to_thread_id_only_when_parent_has_no_message_id(): void
    {
        /** @var array<int, GoogleGmailMessage> $sent */
        $sent = [];

        $sender = $this->makeSender(
            parentHeaders: [
                'message_id' => null,
                'references' => null,
            ],
            sentMessages: $sent,
        );

        $result = $sender->reply(
            credentials: $this->credentials(),
            fromEmail: 'from@example.com',
            to: 'recipient@example.com',
            subject: 'Re: Original',
            htmlBody: '<p>Reply</p>',
            threadId: 'thread-123',
        );

        $this->assertSame('sent-message-id', $result);
        $this->assertCount(1, $sent);

        $raw = $this->decodeRaw($sent[0]);

        $this->assertStringNotContainsString('In-Reply-To:', $raw);
        $this->assertStringNotContainsString('References:', $raw);
        $this->assertSame('thread-123', $sent[0]->getThreadId());
    }

    public function test_reply_falls_back_when_parent_message_id_is_empty_string(): void
    {
        /** @var array<int, GoogleGmailMessage> $sent */
        $sent = [];

        $sender = $this->makeSender(
            parentHeaders: [
                'message_id' => '',
                'references' => 'ignored',
            ],
            sentMessages: $sent,
        );

        $sender->reply(
            credentials: $this->credentials(),
            fromEmail: 'from@example.com',
            to: 'recipient@example.com',
            subject: 'Re: Original',
            htmlBody: '<p>Reply</p>',
            threadId: 'thread-123',
        );

        $raw = $this->decodeRaw($sent[0]);

        $this->assertStringNotContainsString('In-Reply-To:', $raw);
        $this->assertStringNotContainsString('References:', $raw);
    }

    // ------------------------------------------------------------------
    // Regression: non-reply send() must NOT include reply headers
    // ------------------------------------------------------------------

    public function test_send_does_not_include_threading_headers(): void
    {
        /** @var array<int, GoogleGmailMessage> $sent */
        $sent = [];

        $sender = $this->makeSender(
            parentHeaders: ['message_id' => null, 'references' => null],
            sentMessages: $sent,
        );

        $sender->send(
            credentials: $this->credentials(),
            fromEmail: 'from@example.com',
            to: 'recipient@example.com',
            subject: 'Hello',
            htmlBody: '<p>Body</p>',
        );

        $raw = $this->decodeRaw($sent[0]);

        $this->assertStringNotContainsString('In-Reply-To:', $raw);
        $this->assertStringNotContainsString('References:', $raw);
        $this->assertNull($sent[0]->getThreadId());
    }
}
