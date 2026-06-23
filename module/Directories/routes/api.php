<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Directories\Http\Controllers\DirectoryApiSyncController;
use Module\Directories\Http\Controllers\DirectoryCacheController;
use Module\Directories\Http\Controllers\DirectoryCategoryController;
use Module\Directories\Http\Controllers\DirectoryController;
use Module\Directories\Http\Controllers\DirectoryDataController;
use Module\Directories\Http\Controllers\DirectoryDetailController;
use Module\Directories\Http\Controllers\DirectoryFeedController;
use Module\Directories\Http\Controllers\DirectoryImportController;
use Module\Directories\Http\Controllers\DirectoryImportPreviewController;
use Module\Directories\Http\Controllers\DirectoryImportScheduleController;
use Module\Directories\Http\Controllers\DirectoryImportSettingsController;
use Module\Directories\Http\Controllers\DirectoryItemController;
use Module\Directories\Http\Controllers\DirectoryListController;
use Module\Directories\Http\Controllers\DirectoryManualItemController;
use Module\Directories\Http\Controllers\DirectoryVersionController;

// Must be before api/directories/{directory} so "categories" is not captured as {directory}
Route::prefix('api/directories/categories')
    ->name('directories.categories.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(static function (): void {
        Route::middleware('permission:directory_view')->group(static function (): void {
            Route::get('/', [DirectoryCategoryController::class, 'index'])->name('index');
            Route::get('/{category}', [DirectoryCategoryController::class, 'show'])->name('show');
        });

        Route::middleware('permission:directory_create')->group(static function (): void {
            Route::post('/', [DirectoryCategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [DirectoryCategoryController::class, 'update'])->name('update');
        });

        Route::middleware('permission:directory_delete')->group(static function (): void {
            Route::delete('/{category}', [DirectoryCategoryController::class, 'destroy'])->name('destroy');
        });
    });

Route::prefix('api/directories')
    ->name('directories.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::middleware('permission:directory_view')->group(static function (): void {
            Route::get('/', DirectoryListController::class)->name('index');
            Route::get('/feed', DirectoryFeedController::class)->name('feed');
            Route::get('/{directory}', [DirectoryDetailController::class, 'show'])->name('show');
            Route::get('/{directory}/versions', [DirectoryVersionController::class, 'index'])->name('versions.index');
            Route::get('/{directory}/imports', [DirectoryImportController::class, 'index'])->name('imports.index');
            Route::get('/{directory}/items', [DirectoryItemController::class, 'index'])->name('items.index');
        });

        Route::middleware('permission:directory_create')->group(static function (): void {
            Route::post('/', [DirectoryController::class, 'store'])->name('store');
            Route::put('/{directory}', [DirectoryController::class, 'update'])->name('update');
            Route::post('/{directory}/imports', [DirectoryImportController::class, 'store'])->name('imports.store');
            Route::post('/{directory}/import/excel', [DirectoryImportController::class, 'store'])->name(
                'imports.excel'
            );
            Route::post('/{directory}/imports/preview', [DirectoryImportPreviewController::class, 'store'])->name(
                'imports.preview'
            );
            Route::post('/{directory}/sync-api', [DirectoryApiSyncController::class, 'store'])->name('sync-api');
            Route::post('/{directory}/cache/warmup', [DirectoryCacheController::class, 'warmup'])->name('cache.warmup');
            Route::patch('/{directory}/import-settings', [DirectoryImportSettingsController::class, 'update'])->name(
                'import-settings.update'
            );
            Route::put('/{directory}/import-schedule', [DirectoryImportScheduleController::class, 'upsert'])->name(
                'import-schedule.upsert'
            );
            Route::post('/{directory}/items', [DirectoryManualItemController::class, 'store'])->name('items.store');
            Route::put('/{directory}/items/{item}', [DirectoryItemController::class, 'update'])->name('items.update');
        });

        Route::middleware('permission:directory_delete')->group(static function (): void {
            Route::delete('/{directory}', [DirectoryController::class, 'destroy'])->name('destroy');
            Route::delete('/{directory}/items', [DirectoryItemController::class, 'bulkDestroy'])->name(
                'items.bulk-destroy'
            );
            Route::delete('/{directory}/items/{item}', [DirectoryItemController::class, 'destroy'])->name(
                'items.destroy'
            );
        });

        Route::middleware('permission:directory_version_create')->group(static function (): void {
            Route::post('/{directory}/versions', [DirectoryVersionController::class, 'store'])->name('versions.store');
            Route::put('/{directory}/versions/{version}/schema', [DirectoryVersionController::class, 'updateSchema']
            )->whereNumber('version')->name('versions.schema');
            Route::patch(
                '/{directory}/versions/{version}/settings',
                [DirectoryVersionController::class, 'updateSettings']
            )->whereNumber('version')->name('versions.settings');
            Route::patch('/{directory}/versions/{version}/code', [DirectoryVersionController::class, 'updateCode']
            )->whereNumber('version')->name('versions.code');
        });

        Route::middleware('permission:directory_version_delete')->group(static function (): void {
            Route::delete('/{directory}/versions/{version}', [DirectoryVersionController::class, 'destroy']
            )->whereNumber('version')->name('versions.destroy');
        });

        Route::middleware('permission:directory_version_activate')->group(static function (): void {
            Route::patch('/{directory}/versions/{version}/activate', [DirectoryVersionController::class, 'activate']
            )->whereNumber('version')->name('versions.activate');
            Route::post('/{directory}/versions/{version}/activate', [DirectoryVersionController::class, 'activate']
            )->whereNumber('version')->name('versions.activate.post');
        });
    });

Route::get('api/directories/{directory}/data', [DirectoryDataController::class, 'show'])
    ->name('directories.data')
    ->middleware(['api', 'auth:sanctum', 'permission:directory_view']);
