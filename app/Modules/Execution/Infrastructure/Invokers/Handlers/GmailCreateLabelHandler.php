<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Actions\CreateGmailLabelAction;
use App\Modules\Integrations\Application\DTOs\CreateGmailLabelInput;

final class GmailCreateLabelHandler implements ActionHandler
{
    public function __construct(
        private readonly CreateGmailLabelAction $action,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $name = $resolvedConfig['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw ActionInvocationFailed::forInvalidConfig('name');
        }

        $result = $this->action->execute(new CreateGmailLabelInput(
            userId: $userId,
            name: $name,
            connectionId: $connectionId,
        ));

        return [
            'created' => $result->created,
            'label_id' => $result->labelId,
            'name' => $result->name,
        ];
    }
}
