<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Connections\Infrastructure\Providers\ConnectionsServiceProvider::class,
    App\Modules\Integrations\Infrastructure\Providers\IntegrationsServiceProvider::class,
    App\Modules\Automation\Infrastructure\Providers\AutomationServiceProvider::class,
    App\Modules\Execution\Infrastructure\Providers\ExecutionServiceProvider::class,
    App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider::class,
];
