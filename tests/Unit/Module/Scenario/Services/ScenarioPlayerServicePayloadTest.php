<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Projects\Models\Project;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioPlayerService;
use Tests\TestCase;

/**
 * payload() инжектит системные переменные опроса вложенными группами
 * (run / operator / project) — шаблоны вида {{ run.number_formatted }},
 * {{ operator.login }}, {{ project.name }} должны резолвиться в rendered.
 */
final class ScenarioPlayerServicePayloadTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioPlayerService $player;

    private Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = app(ScenarioPlayerService::class);

        $this->scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $this->scenario->id, 'status' => 'active']);

        $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => []],
                [
                    'id' => 'node_block',
                    'type' => 'block',
                    'data' => ['title' => '#{{ run.number_formatted }} · {{ operator.login }} · {{ project.name }}'],
                ],
                ['id' => 'node_end', 'type' => 'end', 'data' => ['title' => 'Готово']],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_block'],
                ['id' => 'e2', 'source' => 'node_block', 'target' => 'node_end'],
            ],
        ]);
    }

    public function test_payload_resolves_nested_system_variables_in_rendered_title(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект Альфа',
            'sitekey' => 'sk-test',
            'host' => 'example.com',
            'is_active' => true,
        ]);
        $operator = User::factory()->create(['login' => 'op_login', 'project_id' => $project->id]);

        $run = $this->player->createRun(new ScenarioRunData(
            scenarioId: $this->scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
            operatorId: $operator->id,
        ));

        $payload = $this->player->payload($run);

        $this->assertSame(
            sprintf('#%s · op_login · Проект Альфа', $run->formattedNumber()),
            $this->renderedTitle($payload),
        );
    }

    public function test_payload_run_block_exposes_nested_system_groups(): void
    {
        $run = $this->player->createRun(new ScenarioRunData(
            scenarioId: $this->scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
        ));

        // Без оператора шаблон operator/project схлопывается в пустую строку, но не падает.
        $payload = $this->player->payload($run);

        $this->assertSame(
            sprintf('#%s ·  · ', $run->formattedNumber()),
            $this->renderedTitle($payload),
        );
    }

    /** @param  array<string, mixed>  $payload */
    private function renderedTitle(array $payload): ?string
    {
        $run = is_array($payload['run'] ?? null) ? $payload['run'] : [];
        $rendered = is_array($run['rendered'] ?? null) ? $run['rendered'] : [];
        $title = $rendered['title'] ?? null;

        return is_string($title) ? $title : null;
    }
}
