<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Proxy\Http\Controllers\PublicProxyController;

Route::match(['GET', 'POST'], '/proxies/{uuid}', PublicProxyController::class)
    ->name('proxy.proxies.receive');
