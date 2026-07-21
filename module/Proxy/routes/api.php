<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Proxy\Http\Controllers\ProxyCategoryController;
use Module\Proxy\Http\Controllers\ProxyConnectionController;
use Module\Proxy\Http\Controllers\ProxyEndpointController;
use Module\Proxy\Http\Controllers\ProxyFeedController;
use Module\Proxy\Http\Controllers\ProxyFieldsController;
use Module\Proxy\Http\Controllers\ProxyRequestLogController;
Route::get('/proxies/{uuid}/fields', ProxyFieldsController::class)
    ->name('proxy.proxies.fields');

Route::prefix('proxy')->name('api.proxy.')->group(static function (): void {
    Route::get('/handlers', [ProxyEndpointController::class, 'handlers'])->name('handlers');
    Route::get('/credential-fields', [ProxyEndpointController::class, 'credentialSchema'])
        ->name('credential-fields');

    Route::get('/credential-types', [ProxyConnectionController::class, 'types'])->name('credential-types');
    Route::get('/connections', [ProxyConnectionController::class, 'index'])->name('connections.index');
    Route::post('/connections', [ProxyConnectionController::class, 'store'])->name('connections.store');
    Route::get('/connections/{connection}', [ProxyConnectionController::class, 'show'])->name('connections.show');
    Route::put('/connections/{connection}', [ProxyConnectionController::class, 'update'])->name('connections.update');
    Route::delete('/connections/{connection}', [ProxyConnectionController::class, 'destroy'])->name('connections.destroy');

    Route::get('/categories', [ProxyCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [ProxyCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [ProxyCategoryController::class, 'show'])->name('categories.show');
    Route::put('/categories/{category}', [ProxyCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [ProxyCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/feed', ProxyFeedController::class)->name('feed');

    Route::get('/endpoints', [ProxyEndpointController::class, 'index'])->name('endpoints.index');
    Route::post('/endpoints', [ProxyEndpointController::class, 'store'])->name('endpoints.store');
    Route::get('/endpoints/{proxy}', [ProxyEndpointController::class, 'show'])->name('endpoints.show');
    Route::put('/endpoints/{proxy}', [ProxyEndpointController::class, 'update'])->name('endpoints.update');
    Route::delete('/endpoints/{proxy}', [ProxyEndpointController::class, 'destroy'])->name('endpoints.destroy');
    Route::get('/endpoints/{proxy}/fields', [ProxyEndpointController::class, 'fields'])->name('endpoints.fields');

    Route::get('/requests', [ProxyRequestLogController::class, 'index'])->name('requests.index');
    Route::get('/requests/{proxyRequest}', [ProxyRequestLogController::class, 'show'])->name('requests.show');
});
