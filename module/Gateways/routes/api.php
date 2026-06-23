<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Gateways\Http\Controllers\IntegrationApiListController;

Route::prefix('api/gateways')
    ->name('gateways.')
    ->group(function (): void {
        Route::get('/', IntegrationApiListController::class)->name('index');
    });
