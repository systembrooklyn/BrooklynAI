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

    public function test_returns_null_for_unknown_handler(): void
    {
        $registry = new HandlerRegistry;

        $this->assertNull($registry->resolve('google.calendar', 'create_event'));
        $this->assertNull($registry->resolve('google.gmail', 'reply_to_email'));
        $this->assertNull($registry->resolve('google.gmail', 'create_draft'));
        $this->assertNull($registry->resolve('unknown.integration', 'x'));
    }
}
