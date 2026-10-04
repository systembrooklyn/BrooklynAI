<?php

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Application\Services\PasswordResetService;
use App\Modules\Identity\Core\Repositories\PasswordResetOtpRepository;
use App\Modules\Identity\Infrastructure\Repositories\EloquentPasswordResetOtpRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/identity.php',
            'identity'
        );

        $this->app->bind(PasswordResetOtpRepository::class, EloquentPasswordResetOtpRepository::class);
        $this->app->singleton(PasswordResetService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../../Lang', 'identity');
        $this->loadViewsFrom(__DIR__ . '/../../Views', 'identity');  

        Route::middleware('api')
            ->group(__DIR__ . '/../../Http/Routes/api.php');
    }
}
