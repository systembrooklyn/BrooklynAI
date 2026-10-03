<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailAddLabelHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailCreateLabelHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailRemoveLabelHandler;
use App\Modules\Integrations\Application\Actions\AddGmailLabelAction;
use App\Modules\Integrations\Application\Actions\CreateGmailLabelAction;
use App\Modules\Integrations\Application\Actions\RemoveGmailLabelAction;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailLabelCreator;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageModifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailLabelActionsHandlerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------
    // Delegation
    // -------------------------------------------------------------------

    public function test_add_label_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailAddLabelHandler(
            new AddGmailLabelAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, [
            'message_id' => 'msg-1',
            'label_id' => 'Label_1',
        ]);

        $this->assertSame([
            'modified' => true,
            'message_id' => 'msg-1',
            'label_id' => 'Label_1',
        ], $result);

        $this->assertCount(1, $modifier->calls);
        $this->assertSame('add_label', $modifier->calls[0]['op']);
        $this->assertSame('msg-1', $modifier->calls[0]['messageId']);
        $this->assertSame('Label_1', $modifier->calls[0]['labelId']);
    }

    public function test_remove_label_handler_delegates_to_modifier(): void
    {
        $user = User::factory()->create();
        $modifier = $this->fakeModifier();

        $handler = new GmailRemoveLabelHandler(
            new RemoveGmailLabelAction($this->fakeResolver(), $modifier)
        );

        $result = $handler->handle((int) $user->id, null, [
            'message_id' => 'msg-2',
            'label_id' => 'Label_2',
        ]);

        $this->assertSame([
            'modified' => true,
            'message_id' => 'msg-2',
            'label_id' => 'Label_2',
        ], $result);

        $this->assertCount(1, $modifier->calls);
        $this->assertSame('remove_label', $modifier->calls[0]['op']);
        $this->assertSame('msg-2', $modifier->calls[0]['messageId']);
        $this->assertSame('Label_2', $modifier->calls[0]['labelId']);
    }

    public function test_create_label_handler_delegates_to_creator(): void
    {
        $user = User::factory()->create();
        $creator = $this->fakeCreator();

        $handler = new GmailCreateLabelHandler(
            new CreateGmailLabelAction($this->fakeResolver(), $creator)
        );

        $result = $handler->handle((int) $user->id, null, [
            'name' => 'My New Label',
        ]);

        $this->assertSame([
            'created' => true,
            'label_id' => 'Label_99',
            'name' => 'My New Label',
        ], $result);

        $this->assertCount(1, $creator->calls);
        $this->assertSame('My New Label', $creator->calls[0]['name']);
    }

    // -------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------

    public function test_add_label_handler_throws_when_message_id_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailAddLabelHandler(
            new AddGmailLabelAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, ['label_id' => 'Label_1']);
    }

    public function test_add_label_handler_throws_when_label_id_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailAddLabelHandler(
            new AddGmailLabelAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('label_id');

        $handler->handle((int) $user->id, null, ['message_id' => 'msg-1']);
    }

    public function test_remove_label_handler_throws_when_message_id_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailRemoveLabelHandler(
            new RemoveGmailLabelAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('message_id');

        $handler->handle((int) $user->id, null, ['label_id' => 'Label_1']);
    }

    public function test_remove_label_handler_throws_when_label_id_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailRemoveLabelHandler(
            new RemoveGmailLabelAction($this->fakeResolver(), $this->fakeModifier())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('label_id');

        $handler->handle((int) $user->id, null, ['message_id' => 'msg-1']);
    }

    public function test_create_label_handler_throws_when_name_missing(): void
    {
        $user = User::factory()->create();

        $handler = new GmailCreateLabelHandler(
            new CreateGmailLabelAction($this->fakeResolver(), $this->fakeCreator())
        );

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('name');

        $handler->handle((int) $user->id, null, []);
    }

    // -------------------------------------------------------------------
    // Connection passthrough
    // -------------------------------------------------------------------

    public function test_add_label_handler_passes_connection_id_through(): void
    {
        $user = User::factory()->create();
        $resolver = $this->fakeResolver();
        $modifier = $this->fakeModifier();

        $handler = new GmailAddLabelHandler(
            new AddGmailLabelAction($resolver, $modifier)
        );

        $handler->handle((int) $user->id, 42, [
            'message_id' => 'msg-1',
            'label_id' => 'Label_1',
        ]);

        $this->assertCount(1, $resolver->calls);
        $this->assertSame(42, $resolver->calls[0]['connectionId']);
    }

    public function test_create_label_handler_passes_connection_id_through(): void
    {
        $user = User::factory()->create();
        $resolver = $this->fakeResolver();
        $creator = $this->fakeCreator();

        $handler = new GmailCreateLabelHandler(
            new CreateGmailLabelAction($resolver, $creator)
        );

        $handler->handle((int) $user->id, 43, ['name' => 'X']);

        $this->assertCount(1, $resolver->calls);
        $this->assertSame(43, $resolver->calls[0]['connectionId']);
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

            public function addLabel(ResolvedGoogleCredentials $credentials, string $messageId, string $labelId): void
            {
                $this->calls[] = ['op' => 'add_label', 'messageId' => $messageId, 'labelId' => $labelId];
            }

            public function removeLabel(ResolvedGoogleCredentials $credentials, string $messageId, string $labelId): void
            {
                $this->calls[] = ['op' => 'remove_label', 'messageId' => $messageId, 'labelId' => $labelId];
            }

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

    /**
     * @return object{ calls: array<int, array<string, mixed>> } & GmailLabelCreator
     */
    private function fakeCreator(): GmailLabelCreator
    {
        return new class extends GmailLabelCreator
        {
            public array $calls = [];

            public function create(ResolvedGoogleCredentials $credentials, string $name): ?array
            {
                $this->calls[] = ['name' => $name];

                return [
                    'label_id' => 'Label_99',
                    'name' => $name,
                ];
            }
        };
    }
}
