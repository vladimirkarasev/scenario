<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Projects\Http\Controllers\ProjectController;

Route::middleware('can:project_view')->group(static function (): void {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
});

Route::middleware('can:project_create')->group(static function (): void {
    Route::post('projects', [ProjectController::class, 'store']);
    Route::put('projects/{project}', [ProjectController::class, 'update']);
    Route::patch('projects/{project}', [ProjectController::class, 'update']);
});

Route::middleware('can:project_delete')->group(static function (): void {
    Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
});
