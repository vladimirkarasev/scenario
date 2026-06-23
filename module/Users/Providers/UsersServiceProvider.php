<?php

declare(strict_types=1);

namespace Module\Users\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');
    }
}
