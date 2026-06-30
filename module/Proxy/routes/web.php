<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Proxy\Http\Controllers\ProxyWebController;

Route::prefix('proxy')->name('proxy.')->group(function (): void {
    Route::get('/endpoints', [ProxyWebController::class, 'proxies'])->name('endpoints');
    Route::get('/connections', [ProxyWebController::class, 'connections'])->name('connections');
    Route::get('/requests', [ProxyWebController::class, 'proxyRequests'])->name('requests');
    Route::get('/requests/{proxyRequest}', [ProxyWebController::class, 'proxyRequestDetail'])->name('requests.show');
});
