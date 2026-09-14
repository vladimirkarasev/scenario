<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Runtime\ScenarioPlayerService;
use Tests\TestCase;

final class ScenarioSuggestVariableTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_object_is_saved_and_resolvable_as_variable(): void
    {
        $player = app(ScenarioPlayerService::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id, 'status' => 'active']);

        $suggestField = ['name' => 'sug', 'varName' => 'place', 'type' => 'suggest'];

        $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => []],
                ['id' => 'node_a', 'type' => 'block', 'data' => ['fields' => [$suggestField]]],
                ['id' => 'node_b', 'type' => 'block', 'data' => ['title' => 'Город: {{ place.address }}']],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_a'],
                ['id' => 'e2', 'source' => 'node_a', 'target' => 'node_b'],
                ['id' => 'e3', 'source' => 'node_b', 'target' => 'node_end'],
            ],
            'schema_json' => [
                'nodes' => [
                    ['id' => 'node_a', 'type' => 'block', 'data' => ['fields' => [$suggestField]]],
                ],
            ],
        ]);

        $run = $player->createRun(new ScenarioRunData(
            scenarioId: $scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
        ));
        $this->assertSame('node_a', $run->current_node_id);

        $suggested = ['address' => 'москва', 'id' => '123'];
        $run = $player->continueRun($run, new ScenarioRunContinueData(['sug' => $suggested], null));

        $this->assertSame('node_b', $run->current_node_id);

        $context = is_array($run->context) ? $run->context : [];
        $nodeA = is_array($context['node_a'] ?? null) ? $context['node_a'] : [];
        $this->assertSame($suggested, $nodeA['sug'] ?? null);

        $payload = $player->payload($run);
        $title = $this->renderedTitle($payload);
        $this->assertSame('Город: москва', $title);
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
