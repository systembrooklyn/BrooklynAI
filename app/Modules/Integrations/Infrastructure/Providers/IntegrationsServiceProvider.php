<?php

namespace App\Modules\Integrations\Infrastructure\Providers;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleAnalyticsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleCalendarIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDocsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDriveIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleSheetsIntegration;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationCatalog::class, function () {
            return new IntegrationCatalog([
                new GmailIntegration,
                new GoogleCalendarIntegration,
                new GoogleSheetsIntegration,
                new GoogleDriveIntegration,
                new GoogleDocsIntegration,
                new GoogleAnalyticsIntegration,
            ]);
        });

        $this->app->singleton(CapabilityScopeMap::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../Lang', 'integrations');

        Route::middleware('api')
            ->group(__DIR__.'/../../Http/Routes/api.php');
    }
}
