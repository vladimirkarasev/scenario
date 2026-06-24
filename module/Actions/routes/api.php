<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Actions\Http\Controllers\ActionCategoryController;
use Module\Actions\Http\Controllers\ActionController;
use Module\Actions\Http\Controllers\ActionFeedController;
use Module\Actions\Http\Controllers\ActionCredentialController;
use Module\Actions\Http\Controllers\ActionListController;
use Module\Actions\Http\Controllers\ActionRunController;
use Module\Actions\Http\Controllers\ActionScheduleController;
use Module\Actions\Http\Controllers\ActionScheduleListController;
use Module\Actions\Http\Controllers\ActionsRunController;
use Module\Actions\Http\Controllers\ActionTypeController;
use Module\Actions\Http\Controllers\DirectorySyncScheduleController;

Route::prefix('api/actions')
    ->name('actions.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/types', [ActionTypeController::class, 'index'])->name('types');
        Route::post('/run', ActionsRunController::class)->name('run');
        Route::get('/', ActionListController::class)->name('index');
        Route::get('/feed', ActionFeedController::class)->name('feed');
        Route::post('/', [ActionController::class, 'store'])->name('store');

        // Разделы (категории) экшенов — ДО /{action}, иначе "categories" поймается как {action}.
        Route::get('/categories', [ActionCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [ActionCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}', [ActionCategoryController::class, 'show'])->name('categories.show');
        Route::put('/categories/{category}', [ActionCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [ActionCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/{action}', [ActionController::class, 'show'])->name('show');
        Route::put('/{action}', [ActionController::class, 'update'])->name('update');
        Route::delete('/{action}', [ActionController::class, 'destroy'])->name('destroy');
        Route::get('/{action}/schedule', [ActionScheduleController::class, 'show'])->name('schedule.show');
        Route::put('/{action}/schedule', [ActionScheduleController::class, 'upsert'])->name('schedule.upsert');
        Route::delete('/{action}/schedule', [ActionScheduleController::class, 'destroy'])->name('schedule.destroy');
    });

// Cron-расписание синхронизации справочника (через Action + ActionSchedule).
Route::prefix('api/directories/{directory}/sync-schedule')
    ->name('directories.sync-schedule.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/', [DirectorySyncScheduleController::class, 'show'])->name('show');
        Route::put('/', [DirectorySyncScheduleController::class, 'upsert'])->name('upsert');
        Route::delete('/', [DirectorySyncScheduleController::class, 'destroy'])->name('destroy');
    });

Route::prefix('api/action-credentials')
    ->name('action-credentials.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/', [ActionCredentialController::class, 'index'])->name('index');
        Route::post('/', [ActionCredentialController::class, 'store'])->name('store');
        Route::put('/{credential}', [ActionCredentialController::class, 'update'])->name('update');
        Route::delete('/{credential}', [ActionCredentialController::class, 'destroy'])->name('destroy');
    });

Route::prefix('api/action-runs')
    ->name('action-runs.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/', [ActionRunController::class, 'index'])->name('index');
        Route::get('/completed', [ActionRunController::class, 'completed'])->name('completed');
        Route::get('/failed', [ActionRunController::class, 'failed'])->name('failed');
    });

Route::prefix('api/action-schedules')
    ->name('action-schedules.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/', ActionScheduleListController::class)->name('index');
    });
