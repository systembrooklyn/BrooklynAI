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

            /*
             * Phase 1 — GmailPollStrategy disabled.
             *
             * The Gmail polling trigger requires gmail.readonly (Restricted),
             * which is outside the send-only release. The GmailPollStrategy
             * class and all its dependencies are preserved in the codebase.
             *
             * To reactivate: uncomment the line below, uncomment the
             * corresponding TriggerDefinition entries in
             * App\Modules\Integrations\Infrastructure\Google\GmailIntegration,
             * re-enable PollGmailCommand below, and restore gmail.readonly in
             * CapabilityScopeMap.
             */
            // $registry->register($app->make(GmailPollStrategy::class));

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

                /*
                 * Phase 1 — PollGmailCommand disabled.
                 *
                 * The Gmail polling command reads Gmail messages and requires
                 * gmail.readonly (Restricted). It is not registered in the
                 * current release. The command class and its private helper
                 * methods remain in the codebase for future reactivation.
                 *
                 * To reactivate: uncomment the line below, restore the
                 * command's handle() body, re-enable the schedule in
                 * routes/console.php, uncomment the TriggerDefinition entries
                 * in GmailIntegration.php, re-register GmailPollStrategy in
                 * the TriggerStrategyRegistry above, and restore gmail.readonly
                 * in CapabilityScopeMap.
                 */
                // PollGmailCommand::class,

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
