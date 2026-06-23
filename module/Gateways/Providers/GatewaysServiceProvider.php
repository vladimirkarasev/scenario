<?php

declare(strict_types=1);

namespace Module\Gateways\Providers;

use Illuminate\Support\ServiceProvider;

final class GatewaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');
    }
}
