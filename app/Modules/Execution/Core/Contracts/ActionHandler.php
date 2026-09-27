<?php

namespace App\Modules\Execution\Core\Contracts;

interface ActionHandler
{
    /**
     * Thin adapter only.
     *
     * Responsibilities (and ONLY these):
     *   1. Receive $userId, $connectionId, $resolvedConfig.
     *   2. Build the corresponding Integration Action input DTO.
     *   3. Delegate to the existing Integration Action.
     *   4. Whitelist the returned output into a plain array.
     *
     * MUST NOT resolve credentials, perform HTTP calls directly,
     * contain business orchestration, or duplicate integration logic.
     *
     * @param  array<string, mixed>  $resolvedConfig
     * @return array<string, mixed>
     *
     * @throws \App\Modules\Execution\Core\Exceptions\ActionInvocationFailed
     */
    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array;
}
