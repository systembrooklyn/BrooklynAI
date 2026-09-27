<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;
use App\Modules\Integrations\Application\Actions\SendGmailEmailAction;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailSendEmailHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_delegates_to_action_and_returns_whitelisted_output(): void
    {
        $user = User::factory()->create(['email' => 'from@example.com']);

        $resolver = $this->fakeResolver();
        $sender = $this->fakeSender();

        $handler = new GmailSendEmailHandler(new SendGmailEmailAction($resolver, $sender));

        $result = $handler->handle(
            (int) $user->id,
            null,
            [
                'to' => 'recipient@example.com',
                'subject' => 'Subject Line',
                'body' => 'Body Content',
            ],
        );

        $this->assertSame([
            'sent' => true,
            'message_id' => 'fake-message-id',
        ], $result);

        $this->assertCount(1, $resolver->calls);
        $this->assertSame((int) $user->id, $resolver->calls[0]['userId']);
        $this->assertNull($resolver->calls[0]['connectionId']);

        $this->assertCount(1, $sender->calls);
        $this->assertSame('from@example.com', $sender->calls[0]['fromEmail']);
        $this->assertSame('recipient@example.com', $sender->calls[0]['to']);
        $this->assertSame('Subject Line', $sender->calls[0]['subject']);
        $this->assertSame('Body Content', $sender->calls[0]['htmlBody']);
    }

    public function test_handler_passes_connection_id_through_to_action(): void
    {
        $user = User::factory()->create(['email' => 'from@example.com']);

        $resolver = $this->fakeResolver();
        $sender = $this->fakeSender();

        $handler = new GmailSendEmailHandler(new SendGmailEmailAction($resolver, $sender));

        $handler->handle(
            (int) $user->id,
            42,
            ['to' => 'r@example.com', 'subject' => 'S', 'body' => 'B'],
        );

        $this->assertCount(1, $resolver->calls);
        $this->assertSame(42, $resolver->calls[0]['connectionId']);
    }

    public function test_handler_throws_when_required_config_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailSendEmailHandler(new SendGmailEmailAction(
            $this->fakeResolver(),
            $this->fakeSender(),
        ));

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('to');

        $handler->handle((int) $user->id, null, [
            'subject' => 'S',
            'body' => 'B',
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

            public function send(
                ResolvedGoogleCredentials $credentials,
                string $fromEmail,
                string $to,
                string $subject,
                string $htmlBody,
            ): ?string {
                $this->calls[] = [
                    'fromEmail' => $fromEmail,
                    'to' => $to,
                    'subject' => $subject,
                    'htmlBody' => $htmlBody,
                ];

                return 'fake-message-id';
            }
        };
    }
}
