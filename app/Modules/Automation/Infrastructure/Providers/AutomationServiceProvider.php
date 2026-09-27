<?php

namespace App\Modules\Automation\Infrastructure\Providers;

use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Infrastructure\Repositories\EloquentWorkflowRepository;
use App\Modules\Automation\Infrastructure\Template\RegexTemplateResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkflowRepository::class, EloquentWorkflowRepository::class);
        $this->app->bind(TemplateResolver::class, RegexTemplateResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../Lang', 'automation');

        Route::middleware('api')
            ->group(__DIR__.'/../../Http/Routes/api.php');
    }
}
