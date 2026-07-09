<?php

declare(strict_types=1);

use App\Http\Controllers\CentrifugoTokenController;
use App\Http\Controllers\Dev\DevAuthApiController;
use App\Http\Middleware\AddApiMeta;
use Module\Users\Http\Controllers\TokenAuthController;
use App\Http\Controllers\EmbedAuth\EmbedAuthExchangeController;
use App\Http\Controllers\EmbedAuth\EmbedAuthRefreshController;
use Illuminate\Support\Facades\Route;

// Единый выход: отзывает access-токен и связанный refresh-токен (если есть).
Route::post('/auth/logout', [TokenAuthController::class, 'logout'])
    ->middleware(['auth:sanctum', AddApiMeta::class]);

Route::prefix('embed/auth')->group(function (): void {
    Route::post('/exchange', EmbedAuthExchangeController::class);
    Route::post('/refresh', EmbedAuthRefreshController::class);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('centrifugo/connection-token', [CentrifugoTokenController::class, 'connectionToken']);
});

if (config('dev_auth.enabled')) {
    Route::prefix('dev/auth')->group(function (): void {
        Route::post('/launch', [DevAuthApiController::class, 'launch'])
            ->name('dev-auth.launch');
        Route::post('/login', [DevAuthApiController::class, 'devLogin'])
            ->name('dev-auth.login');
    });
}
