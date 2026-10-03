<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailReplyToEmailHandler;
use App\Modules\Integrations\Application\Actions\ReplyToGmailEmailAction;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailReplyToEmailHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_delegates_to_action_and_returns_whitelisted_output(): void
    {
        $user = User::factory()->create(['email' => 'from@example.com']);

        $resolver = $this->fakeResolver();
        $sender = $this->fakeSender();

        $handler = new GmailReplyToEmailHandler(new ReplyToGmailEmailAction($resolver, $sender));

        $result = $handler->handle(
            (int) $user->id,
            null,
            [
                'to' => 'recipient@example.com',
                'subject' => 'Re: Original Subject',
                'body' => 'Reply body content',
                'thread_id' => 'thread-abc-123',
            ],
        );

        $this->assertSame([
            'sent' => true,
            'message_id' => 'fake-reply-message-id',
        ], $result);

        $this->assertCount(1, $resolver->calls);
        $this->assertSame((int) $user->id, $resolver->calls[0]['userId']);
        $this->assertNull($resolver->calls[0]['connectionId']);

        $this->assertCount(1, $sender->calls);
        $this->assertSame('from@example.com', $sender->calls[0]['fromEmail']);
        $this->assertSame('recipient@example.com', $sender->calls[0]['to']);
        $this->assertSame('Re: Original Subject', $sender->calls[0]['subject']);
        $this->assertSame('Reply body content', $sender->calls[0]['htmlBody']);
        $this->assertSame('thread-abc-123', $sender->calls[0]['threadId']);
    }

    public function test_handler_passes_connection_id_through_to_action(): void
    {
        $user = User::factory()->create(['email' => 'from@example.com']);

        $resolver = $this->fakeResolver();
        $sender = $this->fakeSender();

        $handler = new GmailReplyToEmailHandler(new ReplyToGmailEmailAction($resolver, $sender));

        $handler->handle(
            (int) $user->id,
            42,
            [
                'to' => 'r@example.com',
                'subject' => 'Re: Subject',
                'body' => 'Body',
                'thread_id' => 'thread-xyz',
            ],
        );

        $this->assertCount(1, $resolver->calls);
        $this->assertSame(42, $resolver->calls[0]['connectionId']);
    }

    public function test_handler_throws_when_thread_id_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailReplyToEmailHandler(new ReplyToGmailEmailAction(
            $this->fakeResolver(),
            $this->fakeSender(),
        ));

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('thread_id');

        $handler->handle((int) $user->id, null, [
            'to' => 'r@example.com',
            'subject' => 'Re: Subject',
            'body' => 'Body',
            // thread_id intentionally omitted
        ]);
    }

    public function test_handler_throws_when_to_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailReplyToEmailHandler(new ReplyToGmailEmailAction(
            $this->fakeResolver(),
            $this->fakeSender(),
        ));

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('to');

        $handler->handle((int) $user->id, null, [
            'subject' => 'Re: Subject',
            'body' => 'Body',
            'thread_id' => 'thread-abc',
        ]);
    }

    /**
     * @return object{ calls: array<int, array<string, mixed>> } & GoogleCredentialsResolver
     */
    private function fakeResolver(): GoogleCredentialsResolver
    {
        return new class implements GoogleCredentialsResolver
        {
            public array $calls = [];

            public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials
            {
                $this->calls[] = ['userId' => $userId, 'connectionId' => $connectionId];

                return new ResolvedGoogleCredentials(
                    accessToken: 'access-token',
                    refreshToken: 'refresh-token',
                    expiresAt: null,
                );
            }
        };
    }

    /**
     * @return object{ calls: array<int, array<string, mixed>> } & GmailEmailSender
     */
    private function fakeSender(): GmailEmailSender
    {
        return new class extends GmailEmailSender
        {
            public array $calls = [];

            public function reply(
                ResolvedGoogleCredentials $credentials,
                string $fromEmail,
                string $to,
                string $subject,
                string $htmlBody,
                string $threadId,
            ): ?string {
                $this->calls[] = [
                    'fromEmail' => $fromEmail,
                    'to' => $to,
                    'subject' => $subject,
                    'htmlBody' => $htmlBody,
                    'threadId' => $threadId,
                ];

                return 'fake-reply-message-id';
            }

            public function send(
                ResolvedGoogleCredentials $credentials,
                string $fromEmail,
                string $to,
                string $subject,
                string $htmlBody,
            ): ?string {
                return 'unused';
            }

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
            {
                throw new \LogicException('createService should not be called in this test.');
            }

            protected function dispatch(
                GoogleGmailService $service,
                GoogleGmailMessage $message,
            ): ?GoogleGmailMessage {
                throw new \LogicException('dispatch should not be called in this test.');
            }
        };
    }
}
