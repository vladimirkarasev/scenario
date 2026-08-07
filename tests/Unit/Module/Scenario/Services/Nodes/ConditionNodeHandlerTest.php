<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Condition\ConditionNodeHandler;
use Tests\TestCase;

final class ConditionNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private ConditionNodeHandler $handler;

    private ScenarioVersion $version;

    private ScenarioRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(ConditionNodeHandler::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision($this->version, [
            'nodes_json' => [
                ['id' => 'node_condition', 'type' => 'condition', 'data' => []],
                ['id' => 'node_a', 'type' => 'block', 'data' => ['title' => 'A']],
                ['id' => 'node_b', 'type' => 'block', 'data' => ['title' => 'B']],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_condition', 'target' => 'node_a', 'sourceHandle' => 'branch_a', 'data' => ['value' => '10']],
                ['id' => 'e2', 'source' => 'node_condition', 'target' => 'node_b', 'sourceHandle' => 'branch_b', 'data' => ['value' => '20']],
                ['id' => 'e3', 'source' => 'node_a', 'target' => 'node_end'],
                ['id' => 'e4', 'source' => 'node_b', 'target' => 'node_end'],
            ],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $this->version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_condition',
            'status' => 'active',
            'context' => ['score' => 10],
        ]);
    }

    public function test_is_interactive_true_for_manual_mode(): void
    {
        $node = ['id' => 'node_condition', 'type' => 'condition', 'data' => ['mode' => 'manual']];

        $this->assertTrue($this->handler->isInteractive($node));
    }

    public function test_is_interactive_true_when_mode_is_absent(): void
    {
        $node = ['id' => 'node_condition', 'type' => 'condition', 'data' => []];

        $this->assertTrue($this->handler->isInteractive($node));
    }

    public function test_is_interactive_false_for_auto_mode(): void
    {
        $node = ['id' => 'node_condition', 'type' => 'condition', 'data' => ['mode' => 'auto']];

        $this->assertFalse($this->handler->isInteractive($node));
    }

    public function test_is_interactive_false_for_manual_mode_with_value_expression(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['mode' => 'manual', 'value' => '{{ score }}'],
        ];

        $this->assertFalse($this->handler->isInteractive($node));
    }

    public function test_render_returns_options_from_explicit_list(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'manual',
                'question' => 'Which way?',
                'options' => [
                    ['label' => 'Left', 'targetNodeId' => 'node_a'],
                    ['label' => 'Right', 'targetNodeId' => 'node_b'],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertSame('condition', $result['type']);
        $this->assertSame('manual', $result['mode']);
        $this->assertSame('Which way?', $result['question']);
        $this->assertTrue($result['hideTitle']);
        $options = $result['options'] ?? null;
        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $firstOption = $options[0] ?? null;
        $this->assertIsArray($firstOption);
        $this->assertSame('Left', $firstOption['label'] ?? null);
        $this->assertSame('node_a', $firstOption['targetNodeId'] ?? null);
    }

    public function test_render_can_show_title(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['hideTitle' => false],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertFalse($result['hideTitle']);
    }

    public function test_render_uses_condition_branches_when_no_explicit_options(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'conditionBranches' => [
                    ['id' => 'branch_a', 'label' => 'Option A'],
                    ['id' => 'branch_b', 'label' => 'Option B'],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, []);

        $options = $result['options'] ?? null;
        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $firstOption = $options[0] ?? null;
        $this->assertIsArray($firstOption);
        $this->assertSame('Option A', $firstOption['label'] ?? null);
        $this->assertSame('node_a', $firstOption['targetNodeId'] ?? null);
    }

    public function test_render_resolves_variables_in_tiptap_content(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'content' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'Возраст: {{ age }}']],
                    ]],
                ],
            ],
        ];

        $result = $this->handler->render($this->version, $node, ['age' => 21]);

        $this->assertSame('Возраст: 21', $result['content']['content'][0]['content'][0]['text']);
    }

    public function test_render_falls_back_to_question_title_text_in_order(): void
    {
        $nodeWithQuestion = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'question' => 'Q',
                'title' => 'T',
                'text' => 'X',
                'options' => [['label' => 'Go', 'targetNodeId' => 'node_a']]
            ],
        ];
        $nodeWithTitle = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['title' => 'T', 'text' => 'X', 'options' => [['label' => 'Go', 'targetNodeId' => 'node_a']]],
        ];
        $nodeWithText = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['text' => 'X', 'options' => [['label' => 'Go', 'targetNodeId' => 'node_a']]],
        ];

        $this->assertSame('Q', $this->handler->render($this->version, $nodeWithQuestion, [])['question']);
        $this->assertSame('T', $this->handler->render($this->version, $nodeWithTitle, [])['question']);
        $this->assertSame('X', $this->handler->render($this->version, $nodeWithText, [])['question']);
    }

    public function test_continue_from_manual_accepts_valid_target(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'manual',
                'options' => [
                    ['label' => 'A', 'targetNodeId' => 'node_a'],
                    ['label' => 'B', 'targetNodeId' => 'node_b'],
                ],
            ],
        ];

        $result = $this->handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: 'node_a'),
        );

        $this->assertSame('node_a', $result);
    }

    public function test_continue_from_manual_rejects_invalid_target(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'manual',
                'options' => [
                    ['label' => 'A', 'targetNodeId' => 'node_a'],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);

        $this->handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: 'node_unknown'),
        );
    }

    public function test_continue_from_auto_evaluates_expression(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => 'score',
                'rules' => [
                    ['operator' => 'equals', 'value' => '10', 'targetNodeId' => 'node_a'],
                ],
                'fallbackTargetNodeId' => 'node_b',
            ],
        ];

        $result = $this->handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: null),
        );

        $this->assertSame('node_a', $result);
    }

    public function test_advance_manual_value_selects_matching_edge(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['mode' => 'manual', 'value' => '{{ score }}'],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_a', $result->nextNodeId);
        $this->assertFalse($result->pause);
    }

    public function test_advance_manual_value_pauses_when_no_edge_matches(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => ['mode' => 'manual', 'value' => '{{ score + 1 }}'],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertNull($result->nextNodeId);
        $this->assertTrue($result->pause);
    }

    public function test_advance_auto_evaluates_arithmetic_expression(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => 'score + 5',
                'rules' => [
                    ['operator' => 'equals', 'value' => '15', 'targetNodeId' => 'node_a'],
                ],
                'fallbackTargetNodeId' => 'node_b',
            ],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_a', $result->nextNodeId);
    }

    public function test_advance_auto_evaluates_template_formula(): void
    {
        $run = $this->makeRun(['a' => 3, 'b' => 7]);
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => '{{ a + b }}',
                'rules' => [
                    ['operator' => 'equals', 'value' => '10', 'targetNodeId' => 'node_a'],
                ],
                'fallbackTargetNodeId' => 'node_b',
            ],
        ];

        $result = $this->handler->advance($run, $node);

        $this->assertSame('node_a', $result->nextNodeId);
    }

    public function test_advance_auto_formula_routes_to_fallback_when_no_rule_matches(): void
    {
        $run = $this->makeRun(['a' => 3, 'b' => 7]);
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => 'a + b',
                'rules' => [
                    ['operator' => 'equals', 'value' => '99', 'targetNodeId' => 'node_a'],
                ],
                'fallbackTargetNodeId' => 'node_b',
            ],
        ];

        $result = $this->handler->advance($run, $node);

        $this->assertSame('node_b', $result->nextNodeId);
    }

    public function test_advance_auto_formula_selects_branch_by_computed_threshold(): void
    {
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => 'score * 2',
                'rules' => [
                    ['operator' => 'equals', 'value' => '30', 'targetNodeId' => 'node_b'],
                    ['operator' => 'equals', 'value' => '20', 'targetNodeId' => 'node_a'],
                ],
                'fallbackTargetNodeId' => 'node_b',
            ],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_a', $result->nextNodeId);
    }

    /** @param  array<string, mixed>  $context */
    private function makeRun(array $context): ScenarioRun
    {
        return ScenarioRun::query()->create([
            'scenario_id' => $this->run->scenario_id,
            'scenario_version_id' => $this->version->id,
            'scenario_version_revision_id' => $this->run->scenario_version_revision_id,
            'current_node_id' => 'node_condition',
            'status' => 'active',
            'context' => $context,
        ]);
    }
}
