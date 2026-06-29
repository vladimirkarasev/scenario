<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Proxy\Http\Controllers\ProxyEndpointController;
use Module\Proxy\Http\Controllers\ProxyFieldsController;
use Module\Proxy\Http\Controllers\ProxyRequestLogController;
use Module\Proxy\Http\Controllers\PublicProxyController;

Route::match(['GET', 'POST'], '/proxies/{uuid}', PublicProxyController::class)
    ->name('proxy.proxies.receive');
Route::get('/proxies/{uuid}/fields', ProxyFieldsController::class)
    ->name('proxy.proxies.fields');

Route::prefix('proxy')->name('api.proxy.')->group(static function (): void {
    Route::get('/handlers', [ProxyEndpointController::class, 'handlers'])->name('handlers');
    Route::get('/credential-fields', [ProxyEndpointController::class, 'credentialSchema'])
        ->name('credential-fields');

    Route::get('/endpoints', [ProxyEndpointController::class, 'index'])->name('endpoints.index');
    Route::post('/endpoints', [ProxyEndpointController::class, 'store'])->name('endpoints.store');
    Route::get('/endpoints/{proxy}', [ProxyEndpointController::class, 'show'])->name('endpoints.show');
    Route::put('/endpoints/{proxy}', [ProxyEndpointController::class, 'update'])->name('endpoints.update');
    Route::delete('/endpoints/{proxy}', [ProxyEndpointController::class, 'destroy'])->name('endpoints.destroy');
    Route::get('/endpoints/{proxy}/fields', [ProxyEndpointController::class, 'fields'])->name('endpoints.fields');

    Route::get('/requests', [ProxyRequestLogController::class, 'index'])->name('requests.index');
    Route::get('/requests/{proxyRequest}', [ProxyRequestLogController::class, 'show'])->name('requests.show');
});
