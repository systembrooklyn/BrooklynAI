<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailArchiveHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailMarkAsReadHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailMarkAsUnreadHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailTrashHandler;
use App\Modules\Integrations\Application\Actions\ArchiveGmailMessageAction;
use App\Modules\Integrations\Application\Actions\MarkGmailAsReadAction;
use App\Modules\Integrations\Application\Actions\MarkGmailAsUnreadAction;
use App\Modules\Integrations\Application\Actions\TrashGmailMessageAction;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageModifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailMessageModificationHandlerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------
    // Delegation — each handler calls the correct modifier method
    // -------------------------------------------------------------------

    public function test_mark_as_read_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailMarkAsReadHandler(
            new MarkGmailAsReadAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, ['message_id' => 'msg-123']);

        $this->assertSame(['modified' => true, 'message_id' => 'msg-123'], $result);
        $this->assertCount(1, $modifier->calls);
        $this->assertSame('mark_as_read', $modifier->calls[0]['op']);
        $this->assertSame('msg-123', $modifier->calls[0]['messageId']);
    }

    public function test_mark_as_unread_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailMarkAsUnreadHandler(
            new MarkGmailAsUnreadAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, ['message_id' => 'msg-456']);

        $this->assertSame(['modified' => true, 'message_id' => 'msg-456'], $result);
        $this->assertCount(1, $modifier->calls);
        $this->assertSame('mark_as_unread', $modifier->calls[0]['op']);
        $this->assertSame('msg-456', $modifier->calls[0]['messageId']);
    }

    public function test_archive_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailArchiveHandler(
            new ArchiveGmailMessageAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, ['message_id' => 'msg-789']);

        $this->assertSame(['modified' => true, 'message_id' => 'msg-789'], $result);
        $this->assertCount(1, $modifier->calls);
        $this->assertSame('archive', $modifier->calls[0]['op']);
        $this->assertSame('msg-789', $modifier->calls[0]['messageId']);
    }

    public function test_trash_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailTrashHandler(
            new TrashGmailMessageAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, ['message_id' => 'msg-xyz']);

        $this->assertSame(['modified' => true, 'message_id' => 'msg-xyz'], $result);
        $this->assertCount(1, $modifier->calls);
        $this->assertSame('trash', $modifier->calls[0]['op']);
        $this->assertSame('msg-xyz', $modifier->calls[0]['messageId']);
    }

    // -------------------------------------------------------------------
    // Validation — each handler rejects a missing message_id
    // -------------------------------------------------------------------

    public function test_mark_as_read_handler_throws_when_message_id_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailMarkAsReadHandler(
            new MarkGmailAsReadAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, []);
    }

    public function test_mark_as_unread_handler_throws_when_message_id_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailMarkAsUnreadHandler(
            new MarkGmailAsUnreadAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, []);
    }

    public function test_archive_handler_throws_when_message_id_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailArchiveHandler(
            new ArchiveGmailMessageAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, []);
    }

    public function test_trash_handler_throws_when_message_id_is_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailTrashHandler(
            new TrashGmailMessageAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, []);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

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
     * @return object{ calls: array<int, array<string, mixed>> } & GmailMessageModifier
     */
    private function fakeModifier(): GmailMessageModifier
    {
        return new class extends GmailMessageModifier
        {
            public array $calls = [];

            public function markAsRead(ResolvedGoogleCredentials $credentials, string $messageId): void
            {
                $this->calls[] = ['op' => 'mark_as_read', 'messageId' => $messageId];
            }

            public function markAsUnread(ResolvedGoogleCredentials $credentials, string $messageId): void
            {
                $this->calls[] = ['op' => 'mark_as_unread', 'messageId' => $messageId];
            }

            public function archive(ResolvedGoogleCredentials $credentials, string $messageId): void
            {
                $this->calls[] = ['op' => 'archive', 'messageId' => $messageId];
            }

            public function trash(ResolvedGoogleCredentials $credentials, string $messageId): void
            {
                $this->calls[] = ['op' => 'trash', 'messageId' => $messageId];
            }
        };
    }
}
