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

// WEB
// GET scenarios - список сценариев
// GET scenarios/{uuid} - сценарий
// GET scenarios/{uuid}/versions - список версий сценария
// GET scenarios/version/{uuid} - версия сценария
// GET scenarios/surveys - список опросов
// GET scenarios/survey/{uuid} - опрос
// GET scenarios/workspace - рабочее пространство сценария

Route::prefix('scenarios/catalog')->group(static function (): void {
    Route::get('', [CatalogController::class, 'index']);
});

// Запуск опроса у пользователя по тегу сценария — для внешних интеграций.
// Сервис-пользователь должен иметь permission scenario_dispatch и project_id.
Route::middleware('permission:scenario_dispatch')->group(static function (): void {
    Route::post('scenarios/dispatch', ScenarioDispatchController::class);
});

// Литеральные сегменты должны быть ДО параметрического {scenario}
Route::get('scenarios/surveys', [SurveysController::class, 'index']);
Route::get('scenarios/survey/{runId}', [SurveysController::class, 'show']);

Route::prefix('scenarios/runner')->group(static function (): void {
    Route::get('', [ScenarioRunController::class, 'index']);
    Route::get('users', [ScenarioRunController::class, 'users']);
    Route::post('', [ScenarioRunController::class, 'store']);
    Route::post('start', [ScenarioRunnerController::class, 'start']);

    Route::get('{runId}', [ScenarioRunController::class, 'show']);
    Route::post('{runId}/continue', [ScenarioRunController::class, 'continue']);
    Route::post('{runId}/jump', [ScenarioRunController::class, 'jump']);
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
