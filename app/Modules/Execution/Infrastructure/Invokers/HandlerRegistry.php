<?php

namespace App\Modules\Execution\Infrastructure\Invokers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;

final class HandlerRegistry
{
    /**
     * @var array<string, class-string<ActionHandler>>
     */
    private const HANDLERS = [
        'google.gmail.send_email' => GmailSendEmailHandler::class,
    ];

    /**
     * @return class-string<ActionHandler>|null
     */
    public function resolve(string $integrationKey, string $actionKey): ?string
    {
        return self::HANDLERS[$integrationKey.'.'.$actionKey] ?? null;
    }
}
