<?php

namespace App\Modules\Execution\Infrastructure\Invokers;

use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use Illuminate\Contracts\Container\Container;

final class CatalogActionInvoker implements ActionInvoker
{
    public function __construct(
        private readonly IntegrationCatalog $catalog,
        private readonly HandlerRegistry $registry,
        private readonly Container $container,
    ) {}

    public function invoke(
        string $integrationKey,
        string $actionKey,
        int $userId,
        ?int $connectionId,
        array $resolvedConfig,
    ): array {
        $integration = $this->catalog->find($integrationKey);

        if ($integration === null) {
            throw ActionInvocationFailed::forUnknownIntegration($integrationKey);
        }

        $actionExists = false;

        foreach ($integration->actions as $candidate) {
            if ($candidate->key === $actionKey) {
                $actionExists = true;
                break;
            }
        }

        if (! $actionExists) {
            throw ActionInvocationFailed::forUnknownAction($integrationKey, $actionKey);
        }

        $handlerClass = $this->registry->resolve($integrationKey, $actionKey);

        if ($handlerClass === null) {
            throw ActionInvocationFailed::forMissingHandler($integrationKey, $actionKey);
        }

        /** @var ActionHandler $handler */
        $handler = $this->container->make($handlerClass);

        return $handler->handle($userId, $connectionId, $resolvedConfig);
    }
}
