<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\IframeTokenController;
use Module\Users\Http\Controllers\PermissionController;
use Module\Users\Http\Controllers\RoleController;
use Module\Users\Http\Controllers\UsersController;
use Module\Users\Http\Controllers\UserTokenController;

Route::middleware('can:user_view')->group(static function (): void {
    Route::get('users', [UsersController::class, 'index']);
    Route::get('users/{user}', [UsersController::class, 'show']);
});

Route::middleware('can:user_create')->group(static function (): void {
    Route::post('users', [UsersController::class, 'store']);
});

Route::middleware('can:user_impersonate')->group(static function (): void {
    Route::post('users/iframe-token', IframeTokenController::class);
});

Route::middleware('can:user_update')->group(static function (): void {
    Route::put('users/{user}', [UsersController::class, 'update']);
});

Route::middleware('can:user_delete')->group(static function (): void {
    Route::delete('users/{user}', [UsersController::class, 'destroy']);
});

Route::middleware('can:user_token_view')->group(static function (): void {
    Route::get('users/{user}/tokens', [UserTokenController::class, 'index']);
});

Route::middleware('can:user_token_manage')->group(static function (): void {
    Route::post('users/{user}/tokens', [UserTokenController::class, 'store']);
    Route::delete('users/{user}/tokens/{tokenId}', [UserTokenController::class, 'destroy']);
});

Route::middleware('can:role_view')->group(static function (): void {
    Route::get('roles', [RoleController::class, 'index']);
});

Route::middleware('can:role_create')->group(static function (): void {
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
});

Route::middleware('can:role_delete')->group(static function (): void {
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
});

Route::get('permissions', [PermissionController::class, 'index']);
