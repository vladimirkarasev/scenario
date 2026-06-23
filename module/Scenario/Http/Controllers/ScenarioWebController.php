<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;

final class ScenarioWebController extends Controller
{
    public function workspace(): Response
    {
        return Inertia::render('Scenario/ScenarioWorkspace');
    }

    public function workspaceScenarios(): Response
    {
        return Inertia::render('Scenario/WorkspaceScenarios');
    }

    public function workspaceRun(string $run): Response
    {
        return Inertia::render('Scenario/ScenarioWorkspace', [
            'initialRunId' => $run,
        ]);
    }

    public function workspaceRunDetail(string $run): Response
    {
        return Inertia::render('Scenario/ScenarioSurveyPlayer', [
            'runId' => $run,
        ]);
    }

    public function workspaceScenario(Scenario $scenario): Response
    {
        return Inertia::render('Scenario/ScenarioWorkspace', [
            'initialScenarioId' => $scenario->id,
        ]);
    }

    public function scenarios(): Response
    {
        return Inertia::render('Scenario/Scenarios');
    }

    public function surveysSupervision(): Response
    {
        return Inertia::render('Scenario/SurveysSupervision');
    }

    public function scenarioRuns(): Response
    {
        return Inertia::render('Scenario/ScenarioRuns');
    }

    public function resumeScenarioRun(ScenarioRun $run): Response
    {
        return Inertia::render('Scenario/ScenarioPlayer', [
            'runId' => $run->id,
            'scenarioId' => $run->scenario_id,
        ]);
    }

    public function editScenario(Scenario $scenario): Response
    {
        return Inertia::render('Scenario/ScenarioEditor', [
            'scenarioId' => $scenario->id,
        ]);
    }

    public function editScenarioVersion(ScenarioVersion $version): Response
    {
        return Inertia::render('Scenario/ScenarioVersionEditor', [
            'scenarioId' => $version->scenario_id,
            'versionId' => $version->id,
        ]);
    }

    public function editScenarioVersionBlock(ScenarioVersion $version, string $block): Response
    {
        return Inertia::render('Scenario/ScenarioBlockEditor', [
            'scenarioId' => $version->scenario_id,
            'versionId' => $version->id,
            'blockId' => $block,
        ]);
    }

    public function editScenarioDraftBlock(Scenario $scenario, string $block): Response
    {
        return Inertia::render('Scenario/ScenarioBlockEditor', [
            'scenarioId' => $scenario->id,
            'versionId' => null,
            'blockId' => $block,
        ]);
    }

    public function surveyRun(ScenarioRun $run): Response
    {
        return Inertia::render('Scenario/ScenarioPlayer', [
            'runId'      => $run->id,
            'scenarioId' => $run->scenario_id,
        ]);
    }
}
