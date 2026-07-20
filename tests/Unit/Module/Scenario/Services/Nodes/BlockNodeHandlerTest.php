<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Block\BlockNodeHandler;
use Tests\TestCase;

final class BlockNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private BlockNodeHandler $handler;

    private ScenarioVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(BlockNodeHandler::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $this->createRevision($this->version, [
            'nodes_json' => [['id' => 'node_block', 'type' => 'block', 'data' => []]],
            'edges_json' => [],
        ]);
    }

    public function test_is_interactive_returns_true_by_default(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => []];

        $this->assertTrue($this->handler->isInteractive($node));
    }

    public function test_is_interactive_returns_false_when_skip_in_survey(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => ['skipInSurvey' => true]];

        $this->assertFalse($this->handler->isInteractive($node));
    }

    public function test_render_returns_title_and_blocks(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Hello', 'blocks' => []]];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertSame('block', $result['type']);
        $this->assertSame('Hello', $result['title']);
        $this->assertIsArray($result['blocks']);
    }

    public function test_render_resolves_template_in_title(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Hello {{ name }}']];

        $result = $this->handler->render($this->version, $node, ['name' => 'Vladimir']);

        $this->assertSame('Hello Vladimir', $result['title']);
    }

    public function test_render_prepends_text_as_rich_text_block(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Title',
                'text' => 'Some rich text',
                'blocks' => [],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertNotEmpty($result['blocks']);
        $this->assertSame('rich_text', $result['blocks'][0]['type']);
        $this->assertSame('node_block_text', $result['blocks'][0]['id']);
    }

    public function test_render_includes_input_field(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    ['id' => 'f1', 'type' => 'input', 'name' => 'email', 'label' => 'Email', 'required' => true],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertNotEmpty($result['blocks']);
        $field = $result['blocks'][0];
        $this->assertSame('input', $field['type']);
        $this->assertSame('email', $field['props']['name']);
        $this->assertSame('Email', $field['props']['label']);
        $this->assertTrue($field['props']['required']);
    }

    public function test_render_includes_suggest_field(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    [
                        'id' => 'f1',
                        'type' => 'suggest',
                        'name' => 'place',
                        'label' => 'Город',
                        'required' => true,
                        'proxyUuid' => 'uuid-123',
                        'labelField' => 'address',
                        'placeholder' => 'Введите город',
                        'multiple' => true,
                    ],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $field = $result['blocks'][0];
        $this->assertSame('suggest', $field['type']);
        $this->assertSame('place', $field['props']['name']);
        $this->assertSame('Город', $field['props']['label']);
        $this->assertTrue($field['props']['required']);
        $this->assertSame('uuid-123', $field['props']['proxyUuid']);
        $this->assertSame('address', $field['props']['labelField']);
        $this->assertSame('Введите город', $field['props']['placeholder']);
        $this->assertTrue($field['props']['multiple']);
    }

    public function test_render_includes_select_field_with_options(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    [
                        'id' => 'f1',
                        'type' => 'select',
                        'name' => 'role',
                        'label' => 'Role',
                        'options' => [
                            ['value' => 'admin', 'label' => 'Admin'],
                            ['value' => 'user', 'label' => 'User'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $field = $result['blocks'][0];
        $this->assertSame('select', $field['type']);
        $this->assertCount(2, $field['props']['options']);
    }

    public function test_render_hides_label_for_hidden_field(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    ['id' => 'f1', 'type' => 'hidden', 'name' => 'token', 'value' => 'abc'],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $field = $result['blocks'][0];
        $this->assertArrayNotHasKey('label', $field['props']);
    }

    public function test_render_includes_and_resolves_layout_document(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'layoutDocument' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello {{ name }}']]],
                    ],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, ['name' => 'Vladimir']);

        $this->assertSame('doc', $result['layoutDocument']['type']);
        $this->assertSame('Hello Vladimir', $result['layoutDocument']['content'][0]['content'][0]['text']);
    }

    public function test_render_layout_document_is_null_when_absent(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Form']];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertNull($result['layoutDocument']);
    }

    public function test_render_includes_label_style_props_when_set(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    [
                        'id' => 'f1',
                        'type' => 'input',
                        'name' => 'email',
                        'label' => 'Email',
                        'labelFontSize' => '24px',
                        'labelColor' => '#ff0000',
                        'labelHighlight' => '#ffff00',
                    ],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $field = $result['blocks'][0];
        $this->assertSame('24px', $field['props']['labelFontSize']);
        $this->assertSame('#ff0000', $field['props']['labelColor']);
        $this->assertSame('#ffff00', $field['props']['labelHighlight']);
    }

    public function test_render_omits_label_style_props_when_unset(): void
    {
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => [
                'title' => 'Form',
                'fields' => [
                    ['id' => 'f1', 'type' => 'input', 'name' => 'email', 'label' => 'Email'],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $field = $result['blocks'][0];
        $this->assertArrayNotHasKey('labelFontSize', $field['props']);
        $this->assertArrayNotHasKey('labelColor', $field['props']);
        $this->assertArrayNotHasKey('labelHighlight', $field['props']);
    }

    public function test_continue_from_merges_input_into_context(): void
    {
        $run = $this->createRun();
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Step']];

        $data = new ScenarioRunContinueData(
            input: ['name' => 'Alice', 'age' => '30'],
            selectedTargetNodeId: null,
        );

        $this->handler->continueFrom($run, $node, $data);

        $run->refresh();
        $this->assertSame('Alice', $run->context['node_block']['name'] ?? null);
        $this->assertSame('30', $run->context['node_block']['age'] ?? null);
    }

    private function createRun(): ScenarioRun
    {
        $scenario = Scenario::query()->create(['name' => 'Test2', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_block', 'type' => 'block', 'data' => []],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_block', 'target' => 'node_end'],
            ],
        ]);

        return ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_block',
            'status' => 'active',
            'context' => [],
        ]);
    }
}
