<?php

namespace App\Modules\Identity\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../Lang', 'identity');

        Route::middleware('api')
            ->group(__DIR__.'/../../Http/Routes/api.php');
    }
}
