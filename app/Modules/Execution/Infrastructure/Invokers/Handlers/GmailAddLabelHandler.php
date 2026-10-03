<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\AddGmailLabelAction;
use App\Modules\Integrations\Application\DTOs\ModifyGmailLabelsInput;

final class GmailAddLabelHandler implements ActionHandler
{
    public function __construct(
        private readonly AddGmailLabelAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $messageId = $resolvedConfig['message_id'] ?? null;
        $labelId = $resolvedConfig['label_id'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            throw ActionInvocationFailed::forInvalidConfig('message_id');
        }

        if (! is_string($labelId) || $labelId === '') {
            throw ActionInvocationFailed::forInvalidConfig('label_id');
        }

        $result = $this->action->execute(new ModifyGmailLabelsInput(
            userId: $userId,
            messageId: $messageId,
            labelId: $labelId,
            connectionId: $connectionId,
        ));

        return [
            'modified' => $result->modified,
            'message_id' => $result->messageId,
            'label_id' => $result->labelId,
        ];
    }
}
