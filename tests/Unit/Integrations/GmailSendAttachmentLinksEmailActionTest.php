<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Application\Actions\SendGmailAttachmentLinksEmailAction;
use App\Modules\Integrations\Application\DTOs\SendGmailAttachmentLinksEmailInput;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use PHPUnit\Framework\TestCase;

final class GmailSendAttachmentLinksEmailActionTest extends TestCase
{
    public function test_single_attachment_with_valid_link_produces_one_anchor(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                [
                    'gmail_attachment_id' => 'att-1',
                    'filename' => 'invoice.pdf',
                    'web_view_link' => 'https://drive.google.com/file/d/abc123/view',
                ],
            ],
            connectionId: null,
        ));

        $this->assertNotNull($sender->lastHtmlBody);
        $this->assertSame(1, substr_count($sender->lastHtmlBody, '<a href='));
        $this->assertStringContainsString(
            '<a href="https://drive.google.com/file/d/abc123/view">invoice.pdf</a>',
            $sender->lastHtmlBody,
        );
    }

    public function test_multiple_attachments_with_valid_links_produce_multiple_anchors_in_order(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                ['filename' => 'one.pdf', 'web_view_link' => 'https://drive.google.com/file/d/1/view'],
                ['filename' => 'two.png', 'web_view_link' => 'https://drive.google.com/file/d/2/view'],
                ['filename' => 'three.docx', 'web_view_link' => 'https://drive.google.com/file/d/3/view'],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertSame(3, substr_count($html, '<a href='));

        $pos1 = strpos($html, 'one.pdf');
        $pos2 = strpos($html, 'two.png');
        $pos3 = strpos($html, 'three.docx');
        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos2);
        $this->assertNotFalse($pos3);
        $this->assertTrue($pos1 < $pos2);
        $this->assertTrue($pos2 < $pos3);
    }

    public function test_attachments_with_null_empty_or_whitespace_links_are_skipped(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                ['filename' => 'valid.pdf', 'web_view_link' => 'https://drive.google.com/file/d/valid/view'],
                ['filename' => 'null-link.pdf', 'web_view_link' => null],
                ['filename' => 'empty-link.pdf', 'web_view_link' => ''],
                ['filename' => 'whitespace-link.pdf', 'web_view_link' => '   '],
                ['filename' => 'missing-link.pdf'],
                ['filename' => 'another-valid.pdf', 'web_view_link' => 'https://drive.google.com/file/d/valid2/view'],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertSame(2, substr_count($html, '<a href='));
        $this->assertStringContainsString('valid.pdf', $html);
        $this->assertStringContainsString('another-valid.pdf', $html);
        $this->assertStringNotContainsString('null-link.pdf', $html);
        $this->assertStringNotContainsString('empty-link.pdf', $html);
        $this->assertStringNotContainsString('whitespace-link.pdf', $html);
        $this->assertStringNotContainsString('missing-link.pdf', $html);
    }

    public function test_zero_attachments_produces_fallback_message(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [],
            connectionId: null,
        ));

        $this->assertSame('<p>Attachments could not be linked.</p>', $sender->lastHtmlBody);
    }

    public function test_attachments_without_usable_links_produce_fallback_message(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                ['filename' => 'a.pdf', 'web_view_link' => null],
                ['filename' => 'b.pdf', 'web_view_link' => ''],
                ['filename' => 'c.pdf'],
            ],
            connectionId: null,
        ));

        $this->assertSame('<p>Attachments could not be linked.</p>', $sender->lastHtmlBody);
    }

    public function test_intro_text_is_rendered_above_the_list(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: 'Files from the latest email:',
            attachments: [
                ['filename' => 'a.pdf', 'web_view_link' => 'https://drive.google.com/file/d/a/view'],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertStringContainsString('<p>Files from the latest email:</p>', $html);
        $this->assertSame(1, substr_count($html, '<a href='));

        $posIntro = strpos($html, 'Files from the latest email');
        $posLink = strpos($html, '<a href=');
        $this->assertNotFalse($posIntro);
        $this->assertNotFalse($posLink);
        $this->assertTrue($posIntro < $posLink);
    }

    public function test_html_escaping_of_filename(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                [
                    'filename' => '<script>alert(1)</script>.pdf',
                    'web_view_link' => 'https://drive.google.com/file/d/safe/view',
                ],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_html_escaping_of_url(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                [
                    'filename' => 'a.pdf',
                    // URL containing characters that must be escaped inside href="..."
                    'web_view_link' => 'https://example.com/?q="><script>alert(1)</script>',
                ],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }

    public function test_html_escaping_of_intro_text(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: 'Hi <b>there</b> & "friend"',
            attachments: [],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_missing_filename_falls_back_to_placeholder(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                ['web_view_link' => 'https://drive.google.com/file/d/x/view'],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertStringContainsString('>Attachment</a>', $html);
    }

    public function test_non_array_entries_are_ignored(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Your attachments',
            introText: null,
            attachments: [
                'not-an-array',
                42,
                null,
                ['filename' => 'real.pdf', 'web_view_link' => 'https://drive.google.com/file/d/real/view'],
            ],
            connectionId: null,
        ));

        $html = (string) $sender->lastHtmlBody;
        $this->assertSame(1, substr_count($html, '<a href='));
        $this->assertStringContainsString('real.pdf', $html);
    }

    public function test_sender_receives_the_configured_fields(): void
    {
        $sender = new CapturingGmailEmailSender;
        $action = $this->makeAction($sender);

        $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Subject Line',
            introText: null,
            attachments: [],
            connectionId: 7,
        ));

        $this->assertSame('sender@example.com', $sender->lastFromEmail);
        $this->assertSame('recipient@example.com', $sender->lastTo);
        $this->assertSame('Subject Line', $sender->lastSubject);
    }

    public function test_send_returns_sent_true_and_message_id(): void
    {
        $sender = new CapturingGmailEmailSender;
        $sender->returnMessageId = 'msg-abc-123';
        $action = $this->makeAction($sender);

        $result = $action->execute(new SendGmailAttachmentLinksEmailInput(
            userId: 1,
            fromEmail: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Subject',
            introText: null,
            attachments: [],
            connectionId: null,
        ));

        $this->assertTrue($result->sent);
        $this->assertSame('msg-abc-123', $result->messageId);
    }

    private function makeAction(CapturingGmailEmailSender $sender): SendGmailAttachmentLinksEmailAction
    {
        $resolver = $this->createMock(GoogleCredentialsResolver::class);
        $resolver->method('resolve')->willReturn(
            new ResolvedGoogleCredentials('access', 'refresh', null),
        );

        return new SendGmailAttachmentLinksEmailAction($resolver, $sender);
    }
}

final class CapturingGmailEmailSender extends GmailEmailSender
{
    public ?string $lastHtmlBody = null;
    public ?string $lastFromEmail = null;
    public ?string $lastTo = null;
    public ?string $lastSubject = null;
    public ?string $returnMessageId = 'msg-id';

    public function send(
        ResolvedGoogleCredentials $credentials,
        string $fromEmail,
        string $to,
        string $subject,
        string $htmlBody,
    ): ?string {
        $this->lastHtmlBody = $htmlBody;
        $this->lastFromEmail = $fromEmail;
        $this->lastTo = $to;
        $this->lastSubject = $subject;

        return $this->returnMessageId;
    }
}
