<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\End\EndNodeHandler;
use Tests\TestCase;

final class EndNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EndNodeHandler $handler;

    private ScenarioVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(EndNodeHandler::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $this->createRevision($this->version, [
            'nodes_json' => [['id' => 'node_end', 'type' => 'end', 'data' => []]],
            'edges_json' => [],
        ]);
    }

    public function test_is_always_interactive(): void
    {
        $this->assertTrue($this->handler->isInteractive(['id' => 'node_end', 'type' => 'end', 'data' => []]));
    }

    public function test_advance_returns_null_next_node(): void
    {
        $scenario = Scenario::query()->create(['name' => 'S', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision(
            $version,
            ['nodes_json' => [['id' => 'node_end', 'type' => 'end', 'data' => []]], 'edges_json' => []]
        );
        $run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_end',
            'status' => 'active',
            'context' => [],
        ]);

        $result = $this->handler->advance($run, ['id' => 'node_end', 'type' => 'end', 'data' => []]);

        $this->assertNull($result->nextNodeId);
    }

    public function test_render_returns_title_and_blocks(): void
    {
        $node = [
            'id' => 'node_end',
            'type' => 'end',
            'data' => [
                'title' => 'Done!',
                'blocks' => [
                    ['id' => 'b1', 'type' => 'paragraph', 'props' => ['text' => 'Thank you']],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertSame('end', $result['type']);
        $this->assertSame('Done!', $result['title']);
        $this->assertTrue($result['hideTitle']);
        $this->assertCount(1, $result['blocks']);
    }

    public function test_render_can_show_title(): void
    {
        $node = ['id' => 'node_end', 'type' => 'end', 'data' => ['hideTitle' => false]];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertFalse($result['hideTitle']);
    }

    public function test_render_uses_default_title_when_absent(): void
    {
        $node = ['id' => 'node_end', 'type' => 'end', 'data' => []];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertSame('Все шаги успешно пройдены', $result['title']);
    }

    public function test_render_resolves_template_in_title(): void
    {
        $node = [
            'id' => 'node_end',
            'type' => 'end',
            'data' => ['title' => 'Thanks, {{ name }}!'],
        ];

        $result = $this->handler->render($this->version, $node, ['name' => 'Alice']);

        $this->assertSame('Thanks, Alice!', $result['title']);
    }
}
