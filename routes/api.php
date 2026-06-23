<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\TokenAuthController;
use App\Http\Controllers\CentrifugoTokenController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\Dev\IframeAuthApiController;
use App\Http\Controllers\EmbedAuth\EmbedAuthExchangeController;
use App\Http\Controllers\EmbedAuth\EmbedAuthLogoutController;
use App\Http\Controllers\EmbedAuth\EmbedAuthRefreshController;
use App\Http\Controllers\EmbedAuth\ProjectUserRegisterController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [TokenAuthController::class, 'login']);
Route::post('/auth/logout', [TokenAuthController::class, 'logout'])->middleware('auth:sanctum');

// Embed auth
Route::post('/project/{projectUuid}/user-register', ProjectUserRegisterController::class);

Route::prefix('embed/auth')->group(function (): void {
    Route::post('/exchange', EmbedAuthExchangeController::class);
    Route::post('/refresh', EmbedAuthRefreshController::class);
    Route::post('/logout', EmbedAuthLogoutController::class)->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('centrifugo/connection-token', [CentrifugoTokenController::class, 'connectionToken']);
    Route::get('centrifugo/subscribe-token', [CentrifugoTokenController::class, 'subscribeToken']);

    Route::get('user', CurrentUserController::class);
    Route::middleware('can:role_view')->group(static function (): void {
        Route::get('roles', [RoleController::class, 'index']);
    });
    Route::middleware('can:role_create')->group(static function (): void {
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::patch('roles/{role}', [RoleController::class, 'update']);
    });
    Route::middleware('can:role_delete')->group(static function (): void {
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    });
    Route::get('permissions', [PermissionController::class, 'index']);
});

if (config('iframe_auth.dev_enabled')) {
    Route::prefix('dev/iframe-auth')->group(function (): void {
        Route::post('/authorize', [IframeAuthApiController::class, 'exchange'])
            ->name('iframe-auth.authorize');
        Route::post('/token', [IframeAuthApiController::class, 'mockToken'])
            ->name('iframe-auth.mock-token');
        Route::post('/login', [IframeAuthApiController::class, 'devLogin'])
            ->name('iframe-auth.dev-login');
    });
}
