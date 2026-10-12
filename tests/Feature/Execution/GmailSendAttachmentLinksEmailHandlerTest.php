<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendAttachmentLinksEmailHandler;
use App\Modules\Integrations\Application\Actions\SendGmailAttachmentLinksEmailAction;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GmailSendAttachmentLinksEmailHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_config_delegates_to_the_action_with_the_expected_fields(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $sender = new CapturingGmailEmailSender;
        $handler = $this->makeHandler($sender);

        $attachments = [
            [
                'gmail_attachment_id' => 'att-1',
                'filename' => 'invoice.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1234,
                'drive_file_id' => 'drive-1',
                'web_view_link' => 'https://drive.google.com/file/d/drive-1/view',
            ],
        ];

        $output = $handler->handle(
            userId: (int) $user->id,
            connectionId: 42,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Your attachments',
                'intro_text' => 'Here are the files:',
                'attachments' => $attachments,
            ],
        );

        $this->assertSame('owner@example.com', $sender->lastFromEmail);
        $this->assertSame('recipient@example.com', $sender->lastTo);
        $this->assertSame('Your attachments', $sender->lastSubject);
        $this->assertNotNull($sender->lastHtmlBody);
        $this->assertStringContainsString('Here are the files:', $sender->lastHtmlBody);
        $this->assertStringContainsString('invoice.pdf', $sender->lastHtmlBody);
        $this->assertStringContainsString('https://drive.google.com/file/d/drive-1/view', $sender->lastHtmlBody);

        $this->assertSame(['sent' => true, 'message_id' => 'msg-id'], $output);
    }

    public function test_intro_text_is_optional_and_null_when_absent(): void
    {
        $user = User::factory()->create();
        $sender = new CapturingGmailEmailSender;
        $handler = $this->makeHandler($sender);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'attachments' => [],
            ],
        );

        // Intro absent → the body is exactly the fallback message.
        $this->assertSame('<p>Attachments could not be linked.</p>', $sender->lastHtmlBody);
    }

    public function test_empty_attachments_array_is_accepted_and_produces_fallback_body(): void
    {
        $user = User::factory()->create();
        $sender = new CapturingGmailEmailSender;
        $handler = $this->makeHandler($sender);

        $output = $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'attachments' => [],
            ],
        );

        $this->assertSame('<p>Attachments could not be linked.</p>', $sender->lastHtmlBody);
        $this->assertSame(['sent' => true, 'message_id' => 'msg-id'], $output);
    }

    public function test_missing_to_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'subject' => 'Subject',
                'attachments' => [],
            ],
        );
    }

    public function test_empty_to_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => '',
                'subject' => 'Subject',
                'attachments' => [],
            ],
        );
    }

    public function test_missing_subject_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'attachments' => [],
            ],
        );
    }

    public function test_missing_attachments_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
            ],
        );
    }

    public function test_non_array_attachments_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'attachments' => 'not-an-array',
            ],
        );
    }

    public function test_non_string_intro_text_is_rejected(): void
    {
        $user = User::factory()->create();
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: (int) $user->id,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'intro_text' => 123,
                'attachments' => [],
            ],
        );
    }

    public function test_missing_user_is_rejected(): void
    {
        $handler = $this->makeHandler(new CapturingGmailEmailSender);

        $this->expectException(ActionInvocationFailed::class);

        $handler->handle(
            userId: 999999,
            connectionId: null,
            resolvedConfig: [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'attachments' => [],
            ],
        );
    }

    private function makeHandler(CapturingGmailEmailSender $sender): GmailSendAttachmentLinksEmailHandler
    {
        $resolver = $this->createMock(GoogleCredentialsResolver::class);
        $resolver->method('resolve')->willReturn(
            new ResolvedGoogleCredentials('access-token', 'refresh-token', null),
        );

        $action = new SendGmailAttachmentLinksEmailAction($resolver, $sender);

        return new GmailSendAttachmentLinksEmailHandler($action);
    }
}

final class CapturingGmailEmailSender extends GmailEmailSender
{
    public ?string $lastFromEmail = null;
    public ?string $lastTo = null;
    public ?string $lastSubject = null;
    public ?string $lastHtmlBody = null;
    public ?string $returnMessageId = 'msg-id';

    public function send(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): ?string {
        $this->lastFromEmail = $fromEmail;
        $this->lastTo = $to;
        $this->lastSubject = $subject;
        $this->lastHtmlBody = $htmlBody;

        return $this->returnMessageId;
    }
}
