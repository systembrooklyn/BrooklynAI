<?php

namespace Tests\Support\Fakes;

use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Closure;

final class FakeActionInvoker implements ActionInvoker
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $invocations = [];

    public ?Closure $onInvoke = null;

    public function invoke(
        string $integrationKey,
        string $actionKey,
        int $userId,
        ?int $connectionId,
        array $resolvedConfig,
    ): array {
        $this->invocations[] = [
            'integration_key' => $integrationKey,
            'action_key' => $actionKey,
            'user_id' => $userId,
            'connection_id' => $connectionId,
            'config' => $resolvedConfig,
        ];

        if ($this->onInvoke !== null) {
            return ($this->onInvoke)(
                $integrationKey,
                $actionKey,
                $userId,
                $connectionId,
                $resolvedConfig,
            );
        }

        return [];
    }
}
