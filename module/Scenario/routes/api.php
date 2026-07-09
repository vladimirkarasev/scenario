<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Scenario\Http\Controllers\CatalogController;
use Module\Scenario\Http\Controllers\ScenarioCategoryController;
use Module\Scenario\Http\Controllers\ScenarioDispatchController;
use Module\Scenario\Http\Controllers\ScenarioFeedController;
use Module\Scenario\Http\Controllers\ScenarioRunController;
use Module\Scenario\Http\Controllers\ScenarioRunnerController;
use Module\Scenario\Http\Controllers\ScenariosController;
use Module\Scenario\Http\Controllers\ScenariosVersionController;
use Module\Scenario\Http\Controllers\SurveysController;
use Module\Projects\Http\Middleware\RequireCurrentProject;
use Module\Scenario\Http\Middleware\RequireScenarioServiceAccount;

Route::prefix('scenarios/catalog')->group(static function (): void {
    Route::get('', [CatalogController::class, 'index']);
});

Route::middleware('permission:scenario_dispatch')->group(static function (): void {
    Route::post('scenarios/dispatch', ScenarioDispatchController::class);
});

Route::middleware(RequireCurrentProject::class)->group(static function (): void {
    Route::get('scenarios/surveys', [SurveysController::class, 'index']);
    Route::get('scenarios/survey/{runId}', [SurveysController::class, 'show']);

    Route::prefix('scenarios/runner')->group(static function (): void {
        Route::get('', [ScenarioRunController::class, 'index']);
        Route::get('users', [ScenarioRunController::class, 'users']);
        Route::post('', [ScenarioRunController::class, 'store'])
            ->middleware(RequireScenarioServiceAccount::class);
        Route::post('start', [ScenarioRunnerController::class, 'start'])
            ->middleware(RequireScenarioServiceAccount::class);

        Route::get('{runId}', [ScenarioRunController::class, 'show']);
        Route::get('{runId}/history', [ScenarioRunController::class, 'history']);
        Route::post('{runId}/continue', [ScenarioRunController::class, 'continue']);
        Route::post('{runId}/jump', [ScenarioRunController::class, 'jump']);
        Route::post('{runId}/retry-action', [ScenarioRunController::class, 'retryAction']);
    });
});

Route::middleware('permission:scenario_view')->group(static function (): void {
    Route::get('scenarios/categories', [ScenarioCategoryController::class, 'index']);
    Route::get('scenarios/categories/{category}', [ScenarioCategoryController::class, 'show']);
    Route::get('scenarios/feed', ScenarioFeedController::class);
    Route::get('scenarios', [ScenariosController::class, 'index']);
    Route::get('scenarios/{scenario}', [ScenariosController::class, 'show']);
});

Route::middleware('permission:scenario_create')->group(static function (): void {
    Route::post('scenarios/categories', [ScenarioCategoryController::class, 'store']);
    Route::put('scenarios/categories/{category}', [ScenarioCategoryController::class, 'update']);
    Route::post('scenarios', [ScenariosController::class, 'store']);
    Route::put('scenarios/{scenario}', [ScenariosController::class, 'update']);
    Route::post('scenarios/{scenario}/duplicate', [ScenariosController::class, 'duplicate']);
});

Route::middleware('permission:scenario_delete')->group(static function (): void {
    Route::delete('scenarios/categories/{category}', [ScenarioCategoryController::class, 'destroy']);
    Route::delete('scenarios/{scenario}', [ScenariosController::class, 'destroy']);
});

Route::middleware('permission:scenario_view')->group(static function (): void {
    Route::get('scenarios/{scenario}/versions', [ScenariosVersionController::class, 'index']);
    Route::get('scenarios/{scenario}/versions/{version}', [ScenariosVersionController::class, 'show']);
});

Route::middleware('permission:scenario_create')->group(static function (): void {
    Route::post('scenarios/{scenario}/versions', [ScenariosVersionController::class, 'store']);
    Route::put('scenarios/{scenario}/versions/{version}', [ScenariosVersionController::class, 'update']);
    Route::post('scenarios/{scenario}/versions/{version}/duplicate', [ScenariosVersionController::class, 'duplicate']);
});

Route::middleware('permission:scenario_delete')->group(static function (): void {
    Route::delete('scenarios/{scenario}/versions/{version}', [ScenariosVersionController::class, 'destroy']);
});
