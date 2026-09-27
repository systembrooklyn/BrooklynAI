<?php

namespace App\Modules\Connections\Infrastructure\Providers;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\Contracts\OAuthGateway;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\Repositories\OAuthStateRepository;
use App\Modules\Connections\Infrastructure\Google\GoogleCredentialResolver;
use App\Modules\Connections\Infrastructure\Google\GoogleOAuthClient;
use App\Modules\Connections\Infrastructure\Repositories\EloquentConnectionRepository;
use App\Modules\Connections\Infrastructure\Repositories\EloquentOAuthStateRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ConnectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/connections.php',
            'connections'
        );

        $this->app->bind(ConnectionRepository::class, EloquentConnectionRepository::class);
        $this->app->bind(OAuthStateRepository::class, EloquentOAuthStateRepository::class);
        $this->app->bind(OAuthGateway::class, GoogleOAuthClient::class);
        $this->app->bind(GoogleCredentialsResolver::class, GoogleCredentialResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../Lang', 'connections');

        Route::middleware('api')
            ->group(__DIR__.'/../../Http/Routes/connections.php');
    }
}
