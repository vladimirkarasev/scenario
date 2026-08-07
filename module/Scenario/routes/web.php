<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Scenario\Http\Controllers\ScenarioWebController;

Route::get('workspace', [ScenarioWebController::class, 'workspace'])
    ->name('workspace');

Route::get('workspace/scenario/{scenario}', [ScenarioWebController::class, 'workspaceScenario'])
    ->name('workspace.scenario');

Route::get('workspace/scenarios', [ScenarioWebController::class, 'workspaceScenarios'])
    ->name('workspace.scenarios');

Route::get('workspace/detail/{run}', [ScenarioWebController::class, 'workspaceRunDetail'])
    ->name('workspace.run.detail');

Route::get('workspace/{run}', [ScenarioWebController::class, 'workspaceRun'])
    ->name('workspace.run');

Route::get('scenarios', [ScenarioWebController::class, 'scenarios'])
    ->name('scenarios');

Route::get('surveys', [ScenarioWebController::class, 'surveysSupervision'])
    ->name('surveys');

Route::get('scenario-runs', [ScenarioWebController::class, 'scenarioRuns'])
    ->name('scenario-runs');

Route::get('scenario-runs/{run}/play', [ScenarioWebController::class, 'resumeScenarioRun'])
    ->name('scenario-runs.play');

Route::get('scenarios/{scenario}/edit', [ScenarioWebController::class, 'editScenario'])
    ->name('scenarios.edit');

Route::get('scenario-versions/{version}/edit', [ScenarioWebController::class, 'editScenarioVersion'])
    ->name('scenario-versions.edit');

Route::get('scenario-versions/{version}/settings', [ScenarioWebController::class, 'scenarioVersionSettings'])
    ->name('scenario-versions.settings');

Route::get('scenario-versions/{version}/history', [ScenarioWebController::class, 'scenarioVersionHistory'])
    ->name('scenario-versions.history');

Route::get('scenario-versions/{version}/blocks/{block}/edit', [ScenarioWebController::class, 'editScenarioVersionBlock']
)
    ->name('scenario-version-blocks.edit');

Route::get('scenarios/{scenario}/blocks/{block}/edit', [ScenarioWebController::class, 'editScenarioDraftBlock'])
    ->name('scenario-draft-blocks.edit');

Route::get('surveys/{run}', [ScenarioWebController::class, 'surveyRun'])
    ->name('surveys.run');
