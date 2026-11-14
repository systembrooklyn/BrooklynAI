<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Infrastructure\Invokers\HandlerRegistry;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    public function test_resolves_google_gmail_send_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertSame(
            GmailSendEmailHandler::class,
            $registry->resolve('google.gmail', 'send_email'),
        );
    }

    /**
     * Phase 1 — all non-send_email Gmail handlers are disabled.
     * See GOOGLE_INTEGRATIONS_MASTER_PLAN §3.
     */
    public function test_does_not_resolve_disabled_gmail_reply_to_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'reply_to_email'));
    }

    public function test_does_not_resolve_disabled_gmail_create_draft(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'create_draft'));
    }

    public function test_does_not_resolve_disabled_gmail_mark_as_read(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'mark_as_read'));
    }

    public function test_does_not_resolve_disabled_gmail_mark_as_unread(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'mark_as_unread'));
    }

    public function test_does_not_resolve_disabled_gmail_archive(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'archive'));
    }

    public function test_does_not_resolve_disabled_gmail_trash(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'trash'));
    }

    public function test_does_not_resolve_disabled_gmail_add_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'add_label'));
    }

    public function test_does_not_resolve_disabled_gmail_remove_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'remove_label'));
    }

    public function test_does_not_resolve_disabled_gmail_create_label(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'create_label'));
    }

    public function test_does_not_resolve_disabled_gmail_send_attachment_links_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.gmail', 'send_attachment_links_email'));
    }

    public function test_returns_null_for_actions_without_a_handler(): void
    {
        $registry = new HandlerRegistry;

        // Catalog-declared but no runtime handler wired yet.
        $this->assertNull($registry->resolve('google.calendar', 'create_event'));

        // Non-existent integration or action.
        $this->assertNull($registry->resolve('unknown.integration', 'x'));
    }
}
