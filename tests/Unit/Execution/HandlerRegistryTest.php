<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Infrastructure\Invokers\HandlerRegistry;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailCreateDraftHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailReplyToEmailHandler;
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

    public function test_resolves_google_gmail_reply_to_email(): void
    {
        $registry = new HandlerRegistry;

        $this->assertSame(
            GmailReplyToEmailHandler::class,
            $registry->resolve('google.gmail', 'reply_to_email'),
        );
    }

    public function test_resolves_google_gmail_create_draft(): void
    {
        $registry = new HandlerRegistry;

        $this->assertSame(
            GmailCreateDraftHandler::class,
            $registry->resolve('google.gmail', 'create_draft'),
        );
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
