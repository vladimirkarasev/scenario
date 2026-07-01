<?php

declare(strict_types=1);

use App\Http\Controllers\CentrifugoTokenController;
use App\Http\Controllers\Dev\IframeAuthApiController;
use Module\Users\Http\Controllers\CurrentUserController;
use Module\Users\Http\Controllers\TokenAuthController;
use App\Http\Controllers\EmbedAuth\EmbedAuthExchangeController;
use App\Http\Controllers\EmbedAuth\EmbedAuthLogoutController;
use App\Http\Controllers\EmbedAuth\EmbedAuthRefreshController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [TokenAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/logout', [TokenAuthController::class, 'logout'])->middleware('auth:sanctum');

Route::prefix('embed/auth')->group(function (): void {
    Route::post('/exchange', EmbedAuthExchangeController::class);
    Route::post('/refresh', EmbedAuthRefreshController::class);
    Route::post('/logout', EmbedAuthLogoutController::class)->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('centrifugo/connection-token', [CentrifugoTokenController::class, 'connectionToken']);
    Route::get('centrifugo/subscribe-token', [CentrifugoTokenController::class, 'subscribeToken']);

    Route::get('user', CurrentUserController::class);
});

if (config('iframe_auth.dev_enabled')) {
    Route::prefix('dev/iframe-auth')->group(function (): void {
        Route::post('/launch', [IframeAuthApiController::class, 'launch'])
            ->name('iframe-auth.launch');
        Route::post('/login', [IframeAuthApiController::class, 'devLogin'])
            ->name('iframe-auth.dev-login');
    });
}
