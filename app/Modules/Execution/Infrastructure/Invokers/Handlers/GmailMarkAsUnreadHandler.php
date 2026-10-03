<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\MarkGmailAsUnreadAction;
use App\Modules\Integrations\Application\DTOs\ModifyGmailMessageInput;

final class GmailMarkAsUnreadHandler implements ActionHandler
{
    public function __construct(
        private readonly MarkGmailAsUnreadAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $messageId = $resolvedConfig['message_id'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            throw ActionInvocationFailed::forInvalidConfig('message_id');
        }

        $result = $this->action->execute(new ModifyGmailMessageInput(
            userId: $userId,
            messageId: $messageId,
            connectionId: $connectionId,
        ));

        return [
            'modified' => $result->modified,
            'message_id' => $result->messageId,
        ];
    }
}
