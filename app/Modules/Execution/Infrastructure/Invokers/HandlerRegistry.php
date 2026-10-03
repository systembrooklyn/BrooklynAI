<?php

namespace App\Modules\Execution\Infrastructure\Invokers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailAddLabelHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailArchiveHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailCreateDraftHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailCreateLabelHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailMarkAsReadHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailMarkAsUnreadHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailRemoveLabelHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailReplyToEmailHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailSendEmailHandler;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\GmailTrashHandler;

final class HandlerRegistry
{
    /**
     * @var array<string, class-string<ActionHandler>>
     */
    private const HANDLERS = [
        'google.gmail.send_email' => GmailSendEmailHandler::class,
        'google.gmail.reply_to_email' => GmailReplyToEmailHandler::class,
        'google.gmail.create_draft' => GmailCreateDraftHandler::class,
        'google.gmail.mark_as_read' => GmailMarkAsReadHandler::class,
        'google.gmail.mark_as_unread' => GmailMarkAsUnreadHandler::class,
        'google.gmail.archive' => GmailArchiveHandler::class,
        'google.gmail.trash' => GmailTrashHandler::class,
        'google.gmail.add_label' => GmailAddLabelHandler::class,
        'google.gmail.remove_label' => GmailRemoveLabelHandler::class,
        'google.gmail.create_label' => GmailCreateLabelHandler::class,
    ];

    /**
     * @return class-string<ActionHandler>|null
     */
    public function resolve(string $integrationKey, string $actionKey): ?string
    {
        return self::HANDLERS[$integrationKey.'.'.$actionKey] ?? null;
    }
}
