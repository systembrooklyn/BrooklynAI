<?php

namespace Tests\Feature\Execution;

use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use Tests\TestCase;

class CatalogActionInvokerTest extends TestCase
{
    private ActionInvoker $invoker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoker = $this->app->make(ActionInvoker::class);
    }

    public function test_rejects_unknown_integration(): void
    {
        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('Unknown integration');

        $this->invoker->invoke('unknown.integration', 'x', 1, null, []);
    }

    public function test_rejects_unknown_action(): void
    {
        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('Unknown action');

        $this->invoker->invoke('google.gmail', 'this_action_does_not_exist', 1, null, []);
    }

    public function test_rejects_missing_handler(): void
    {
        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('No handler registered');

        // `google.gmail.reply_to_email` is declared in the catalog but
        // intentionally has no handler wired in Batch 6.4.
        $this->invoker->invoke('google.gmail', 'reply_to_email', 1, null, []);
    }
}
