<?php

declare(strict_types=1);

use App\Http\Controllers\CentrifugoTokenController;
use App\Http\Controllers\Dev\DevAuthApiController;
use App\Http\Controllers\EmbedAuth\EmbedAuthExchangeController;
use App\Http\Controllers\EmbedAuth\EmbedAuthRefreshController;
use App\Http\Controllers\ExpressionRenderBatchController;
use App\Http\Controllers\ExpressionRenderController;
use App\Http\Middleware\AddApiMeta;
use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\TokenAuthController;

// Единый выход: отзывает access-токен и связанный refresh-токен (если есть).
Route::post('/auth/logout', [TokenAuthController::class, 'logout'])
    ->middleware(['auth:sanctum', AddApiMeta::class]);

Route::prefix('embed/auth')->group(function (): void {
    Route::post('/exchange', EmbedAuthExchangeController::class);
    Route::post('/refresh', EmbedAuthRefreshController::class);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('centrifugo/connection-token', [CentrifugoTokenController::class, 'connectionToken']);

    Route::post('expression/render', ExpressionRenderController::class)
        ->middleware([AddApiMeta::class, 'throttle:expression-render'])
        ->name('expression.render');

    Route::post('expression/render-batch', ExpressionRenderBatchController::class)
        ->middleware([AddApiMeta::class, 'throttle:expression-render-batch'])
        ->name('expression.render-batch');
});

if (config('dev_auth.enabled')) {
    Route::prefix('dev/auth')->group(function (): void {
        Route::post('/launch', [DevAuthApiController::class, 'launch'])
            ->name('dev-auth.launch');
        Route::post('/impersonate', [DevAuthApiController::class, 'impersonate'])
            ->middleware(AddApiMeta::class)
            ->name('dev-auth.impersonate');
        Route::post('/login', [DevAuthApiController::class, 'devLogin'])
            ->name('dev-auth.login');
    });
}
