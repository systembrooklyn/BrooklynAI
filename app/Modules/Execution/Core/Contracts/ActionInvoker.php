<?php

namespace App\Modules\Execution\Core\Contracts;

interface ActionInvoker
{
    /**
     * @param  array<string, mixed>  $resolvedConfig
     * @return array<string, mixed>
     *
     * @throws \App\Modules\Execution\Core\Exceptions\ActionInvocationFailed
     */
    public function invoke(
        string $integrationKey,
        string $actionKey,
        int $userId,
        ?int $connectionId,
        array $resolvedConfig,
    ): array;
}
