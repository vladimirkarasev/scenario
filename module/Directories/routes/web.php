<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Directories\Http\Controllers\DirectoryPageController;

Route::get('directories', [DirectoryPageController::class, 'index'])
    ->name('directories');

Route::get('directories/{directory}', [DirectoryPageController::class, 'show'])
    ->name('directories.show');

Route::get('directories/{directory}/settings', [DirectoryPageController::class, 'settings'])
    ->name('directories.settings');

Route::get('directories/{directory}/versions/{version}', [DirectoryPageController::class, 'version'])
    ->name('directories.version');
