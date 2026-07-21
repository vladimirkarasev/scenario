<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Temporal\Activities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Temporal\Activities\ResumeScenarioRunActivity;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Tests\TestCase;

final class ResumeScenarioRunActivityTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);

        $revision = $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_action', 'type' => 'action', 'data' => []],
                ['id' => 'node_next', 'type' => 'block', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_action', 'target' => 'node_next'],
            ],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_action',
            'status' => 'active',
            'context' => [],
        ]);
    }

    public function test_resume_on_success_merges_context_and_advances_run(): void
    {
        app(ResumeScenarioRunActivity::class)->resume(
            (string) $this->run->id,
            'node_action',
            true,
            ['email' => 'user@example.com'],
        );

        $run = $this->run->refresh();

        $this->assertSame('node_next', $run->current_node_id);
        $this->assertSame('user@example.com', $run->context['email'] ?? null);
        $this->assertSame('done', $run->context['_action_runs']['node_action'] ?? null);
    }

    public function test_resume_on_failure_keeps_run_on_node(): void
    {
        app(ResumeScenarioRunActivity::class)->resume(
            (string) $this->run->id,
            'node_action',
            false,
            ['failed_action_id' => 'a1', 'failed_error' => 'boom'],
        );

        $run = $this->run->refresh();

        $this->assertSame('node_action', $run->current_node_id);
        $this->assertSame('failed', $run->context['_action_runs']['node_action'] ?? null);
    }

    public function test_resume_for_unknown_run_is_a_noop(): void
    {
        app(ResumeScenarioRunActivity::class)->resume(
            '00000000-0000-0000-0000-000000000000',
            'node_action',
            true,
            [],
        );

        $this->assertSame('node_action', $this->run->refresh()->current_node_id);
    }
}
