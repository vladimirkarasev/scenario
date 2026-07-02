<?php

declare(strict_types=1);

use App\Http\Controllers\CentrifugoTokenController;
use App\Http\Controllers\Dev\DevAuthApiController;
use Module\Users\Http\Controllers\CurrentUserController;
use Module\Users\Http\Controllers\TokenAuthController;
use App\Http\Controllers\EmbedAuth\EmbedAuthExchangeController;
use App\Http\Controllers\EmbedAuth\EmbedAuthRefreshController;
use Illuminate\Support\Facades\Route;

// Единый выход: отзывает access-токен и связанный refresh-токен (если есть).
Route::post('/auth/login', [TokenAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/logout', [TokenAuthController::class, 'logout'])->middleware('auth:sanctum');

Route::prefix('embed/auth')->group(function (): void {
    Route::post('/exchange', EmbedAuthExchangeController::class);
    Route::post('/refresh', EmbedAuthRefreshController::class);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('centrifugo/connection-token', [CentrifugoTokenController::class, 'connectionToken']);
    Route::get('centrifugo/subscribe-token', [CentrifugoTokenController::class, 'subscribeToken']);

    Route::get('user', CurrentUserController::class);
});

if (config('dev_auth.enabled')) {
    Route::prefix('dev/auth')->group(function (): void {
        Route::post('/launch', [DevAuthApiController::class, 'launch'])
            ->name('dev-auth.launch');
        Route::post('/login', [DevAuthApiController::class, 'devLogin'])
            ->name('dev-auth.login');
    });
}
