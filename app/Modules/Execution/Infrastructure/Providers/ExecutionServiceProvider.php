<?php

namespace App\Modules\Execution\Infrastructure\Providers;

use App\Modules\Execution\Application\Services\WorkflowSnapshotBuilder;
use App\Modules\Execution\Console\Commands\PollGmailCommand;
use App\Modules\Execution\Console\Commands\RecoverFailedPollExecutionsCommand;
use App\Modules\Execution\Console\Commands\RunScheduledWorkflowsCommand;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Infrastructure\Invokers\CatalogActionInvoker;
use App\Modules\Execution\Infrastructure\Invokers\HandlerRegistry;
use App\Modules\Execution\Infrastructure\Repositories\EloquentExecutionRepository;
use App\Modules\Execution\Application\Services\TriggerStrategyRegistry;
use App\Modules\Execution\Application\Strategies\GmailPollStrategy;
use App\Modules\Execution\Application\Strategies\ScheduleStrategy;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ExecutionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExecutionRepository::class, EloquentExecutionRepository::class);
        $this->app->singleton(HandlerRegistry::class);
        $this->app->singleton(WorkflowSnapshotBuilder::class);
        $this->app->bind(ActionInvoker::class, CatalogActionInvoker::class);

        $this->app->singleton(TriggerStrategyRegistry::class, function ($app) {
            $registry = new TriggerStrategyRegistry;
            $registry->register($app->make(ScheduleStrategy::class));
            $registry->register($app->make(GmailPollStrategy::class));
            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../../Lang', 'execution');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunScheduledWorkflowsCommand::class,
                PollGmailCommand::class,
                RecoverFailedPollExecutionsCommand::class,
            ]);
        }

        Route::middleware('api')
            ->group(__DIR__ . '/../../Http/Routes/api.php');

        // Internal scheduler endpoint — authenticated by dedicated middleware,
        // not by Sanctum. Kept in a separate route file to avoid accidental
        // auth:sanctum wrapping.
        Route::middleware('api')
            ->group(__DIR__ . '/../../Http/Routes/internal.php');
    }
}
