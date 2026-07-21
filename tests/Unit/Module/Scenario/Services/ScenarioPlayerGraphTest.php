<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioPlayerService;
use Tests\TestCase;

final class ScenarioPlayerGraphTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioPlayerService $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = app(ScenarioPlayerService::class);
    }

    public function test_start_block_auto_condition_action_end_happy_path(): void
    {
        Bus::fake();
        $scenario = $this->scenarioWithRevision(
            nodes: [
                $this->node('start', 'start'),
                $this->node('form', 'block', [
                    'title' => 'Расчёт',
                    'fields' => [
                        $this->field('price', 'number', 'price'),
                        $this->field('quantity', 'number', 'quantity'),
                        $this->field('discount', 'number', 'discount'),
                    ],
                ]),
                $this->node('condition', 'condition', [
                    'mode' => 'auto',
                    'expression' => '(price * quantity) - discount',
                    'rules' => [
                        ['operator' => 'greater_or_equal', 'value' => 100, 'targetNodeId' => 'action'],
                    ],
                    'fallbackTargetNodeId' => 'small_end',
                ]),
                $this->node('action', 'action', ['skipInSurvey' => true, 'action_items' => []]),
                $this->node('large_end', 'end', ['title' => 'Крупный заказ: {{ price * quantity - discount }}']),
                $this->node('small_end', 'end', ['title' => 'Обычный заказ']),
            ],
            edges: [
                $this->edge('start', 'form'),
                $this->edge('form', 'condition'),
                $this->edge('condition', 'action'),
                $this->edge('condition', 'small_end'),
                $this->edge('action', 'large_end'),
            ],
        );

        $run = $this->createRun($scenario);
        $this->assertSame('form', $run->current_node_id);

        $run = $this->player->continueRun($run, new ScenarioRunContinueData([
            'price' => 30,
            'quantity' => 4,
            'discount' => 15,
        ], null));

        $this->assertSame('completed', $run->status->value);
        $this->assertSame('large_end', $run->current_node_id);
        $this->assertSame('Крупный заказ: 105', $this->renderedTitle($run));
        Bus::assertNothingDispatched();
    }

    public function test_auto_condition_follows_fallback_for_false_math_result(): void
    {
        $scenario = $this->scenarioWithRevision(
            nodes: [
                $this->node('start', 'start'),
                $this->node('form', 'block', [
                    'fields' => [$this->field('score', 'number', 'score')],
                ]),
                $this->node('condition', 'condition', [
                    'mode' => 'auto',
                    'expression' => 'score * 2',
                    'rules' => [
                        ['operator' => 'greater_than', 'value' => 100, 'targetNodeId' => 'passed'],
                    ],
                    'fallbackTargetNodeId' => 'failed',
                ]),
                $this->node('passed', 'end', ['title' => 'Passed']),
                $this->node('failed', 'end', ['title' => 'Failed']),
            ],
            edges: [
                $this->edge('start', 'form'),
                $this->edge('form', 'condition'),
                $this->edge('condition', 'passed'),
                $this->edge('condition', 'failed'),
            ],
        );

        $run = $this->player->continueRun(
            $this->createRun($scenario),
            new ScenarioRunContinueData(['score' => 40], null),
        );

        $this->assertSame('failed', $run->current_node_id);
        $this->assertSame('Failed', $this->renderedTitle($run));
    }

    public function test_boolean_condition_uses_block_variables(): void
    {
        $scenario = $this->scenarioWithRevision(
            nodes: [
                $this->node('start', 'start'),
                $this->node('form', 'block', [
                    'fields' => [
                        $this->field('age', 'number', 'age'),
                        $this->field('score', 'number', 'score'),
                    ],
                ]),
                $this->node('condition', 'condition', [
                    'mode' => 'auto',
                    'expression' => 'age >= 18 and score >= 70',
                    'rules' => [
                        ['operator' => 'equals', 'value' => true, 'targetNodeId' => 'accepted'],
                    ],
                    'fallbackTargetNodeId' => 'rejected',
                ]),
                $this->node('accepted', 'end', ['title' => 'Accepted {{ age }}/{{ score }}']),
                $this->node('rejected', 'end', ['title' => 'Rejected']),
            ],
            edges: [
                $this->edge('start', 'form'),
                $this->edge('form', 'condition'),
                $this->edge('condition', 'accepted'),
                $this->edge('condition', 'rejected'),
            ],
        );

        $run = $this->player->continueRun(
            $this->createRun($scenario),
            new ScenarioRunContinueData(['age' => 20, 'score' => 75], null),
        );

        $this->assertSame('accepted', $run->current_node_id);
        $this->assertSame('Accepted 20/75', $this->renderedTitle($run));
    }

    public function test_manual_condition_waits_for_selection_and_follows_selected_branch(): void
    {
        $scenario = $this->scenarioWithRevision(
            nodes: [
                $this->node('start', 'start'),
                $this->node('choice', 'condition', [
                    'mode' => 'manual',
                    'question' => 'Куда перейти?',
                    'options' => [
                        ['label' => 'Влево', 'targetNodeId' => 'left'],
                        ['label' => 'Вправо', 'targetNodeId' => 'right'],
                    ],
                ]),
                $this->node('left', 'end', ['title' => 'Left']),
                $this->node('right', 'end', ['title' => 'Right']),
            ],
            edges: [
                $this->edge('start', 'choice'),
                $this->edge('choice', 'left'),
                $this->edge('choice', 'right'),
            ],
        );

        $run = $this->createRun($scenario);
        $this->assertSame('choice', $run->current_node_id);
        $this->assertSame('condition', $run->steps()->first()?->node_type->value);

        $run = $this->player->continueRun($run, new ScenarioRunContinueData([], 'right'));
        $this->assertSame('right', $run->current_node_id);
        $this->assertSame('Right', $this->renderedTitle($run));
    }

    public function test_scenario_link_switches_graph_and_continues_to_target_block(): void
    {
        $target = $this->scenarioWithRevision(
            nodes: [
                $this->node('target_start', 'start'),
                $this->node('target_form', 'block', [
                    'title' => 'Target {{ source_value }}',
                    'fields' => [$this->field('answer', 'input', 'answer')],
                ]),
                $this->node('target_end', 'end', ['title' => 'Done {{ answer }}']),
            ],
            edges: [
                $this->edge('target_start', 'target_form'),
                $this->edge('target_form', 'target_end'),
            ],
        );
        $targetVersion = $target->versions()->firstOrFail();

        $source = $this->scenarioWithRevision(
            nodes: [
                $this->node('source_start', 'start'),
                $this->node('link', 'scenario_link', [
                    'targetScenarioId' => $target->id,
                    'targetVersionId' => $targetVersion->id,
                ]),
            ],
            edges: [$this->edge('source_start', 'link')],
        );

        $run = $this->player->createRun(new ScenarioRunData(
            scenarioId: $source->id,
            scenarioVersionId: null,
            context: ['source_value' => 'preserved'],
            userData: [],
        ));

        $this->assertSame($source->id, $run->scenario_id);
        $this->assertSame($targetVersion->id, $run->scenario_version_id);
        $this->assertSame('target_form', $run->current_node_id);
        $this->assertSame('Target preserved', $this->renderedTitle($run));

        $run = $this->player->continueRun($run, new ScenarioRunContinueData(['answer' => 'OK'], null));
        $this->assertSame('Done OK', $this->renderedTitle($run));
    }

    private function createRun(Scenario $scenario): ScenarioRun
    {
        return $this->player->createRun(new ScenarioRunData(
            scenarioId: $scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
        ));
    }

    /** @param list<array<string, mixed>> $nodes @param list<array<string, mixed>> $edges */
    private function scenarioWithRevision(array $nodes, array $edges): Scenario
    {
        $scenario = Scenario::query()->create(['name' => 'Player test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create([
            'scenario_id' => $scenario->id,
            'status' => 'active',
        ]);
        $this->createRevision($version, [
            'schema_json' => ['nodes' => $nodes, 'edges' => $edges],
            'nodes_json' => $nodes,
            'edges_json' => $edges,
        ]);
        $scenario->forceFill(['active_version_id' => $version->id])->save();

        return $scenario;
    }

    /** @return array<string, mixed> */
    private function node(string $id, string $type, array $data = []): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data];
    }

    /** @return array<string, string> */
    private function edge(string $source, string $target): array
    {
        return ['id' => $source.'-'.$target, 'source' => $source, 'target' => $target];
    }

    /** @return array<string, mixed> */
    private function field(string $name, string $type, string $varName): array
    {
        return [
            'id' => $name,
            'type' => $type,
            'name' => $name,
            'varName' => $varName,
            'label' => ucfirst($name),
            'required' => true,
            'value' => '',
        ];
    }

    private function renderedTitle(ScenarioRun $run): ?string
    {
        $payload = $this->player->payload($run);
        $rendered = $payload['run']['rendered'] ?? null;

        return is_array($rendered) && is_string($rendered['title'] ?? null)
            ? $rendered['title']
            : null;
    }
}
