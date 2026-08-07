<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Models\Action;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Action\ActionNodeHandler;
use Tests\Stubs\FakeRunActionsWorkflowStarter;
use Tests\TestCase;

final class ActionNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private const string ACTION_A = '00000000-0000-0000-0000-0000000000a1';

    private const string ACTION_B = '00000000-0000-0000-0000-0000000000a2';

    private const string ACTION_BEFORE = '00000000-0000-0000-0000-0000000000b1';

    private const string ACTION_ERROR = '00000000-0000-0000-0000-0000000000e1';

    private ActionNodeHandler $handler;

    private ScenarioVersion $version;

    private ScenarioRun $run;

    private FakeRunActionsWorkflowStarter $workflowStarter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowStarter = new FakeRunActionsWorkflowStarter();
        $this->app->instance(RunActionsWorkflowStarterInterface::class, $this->workflowStarter);

        $this->handler = app(ActionNodeHandler::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);

        $revision = $this->createRevision($this->version, [
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
            'scenario_version_id' => $this->version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_action',
            'status' => 'active',
            'context' => ['user' => ['email' => 'a@example.com']],
        ]);
        $this->run->setRelation('version', $this->version);

        foreach ([self::ACTION_A, self::ACTION_B, self::ACTION_BEFORE, self::ACTION_ERROR] as $id) {
            Action::query()->create([
                'id' => $id,
                'name' => 'Action '.$id,
                'slug' => 'k_'.substr($id, -4),
                'code' => 'c_'.substr($id, -4),
                'type' => 'template_file',
                'is_active' => true,
                'config' => [],
                'schema' => [],
                'ui_schema' => [],
                'input_fields' => [],
            ]);
        }
    }

    public function test_is_interactive_when_not_skipped(): void
    {
        $node = ['id' => 'node_action', 'type' => 'action', 'data' => ['skipInSurvey' => false]];
        $this->assertTrue($this->handler->isInteractive($node));

        $node['data']['skipInSurvey'] = true;
        $this->assertFalse($this->handler->isInteractive($node));
    }

    public function test_render_hides_title_by_default_and_can_show_it(): void
    {
        $hidden = $this->handler->render(
            $this->version,
            ['id' => 'node_action', 'type' => 'action', 'data' => []],
            [],
        );
        $visible = $this->handler->render(
            $this->version,
            ['id' => 'node_action', 'type' => 'action', 'data' => ['hideTitle' => false]],
            [],
        );

        $this->assertTrue($hidden['data']['hideTitle']);
        $this->assertFalse($visible['data']['hideTitle']);
    }

    public function test_advance_dispatches_sequential_chain_for_action_items(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'skipInSurvey' => true,
                'execution_mode' => 'sequential',
                'action_items' => [
                    [
                        'code' => 'tpl',
                        'action_id' => self::ACTION_A,
                        'input' => ['to' => '{{ user.email }}'],
                        'backoff' => [0, 60],
                        'delay_before' => 30,
                    ],
                    ['code' => 'send', 'action_id' => self::ACTION_B, 'input' => []],
                ],
            ],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_next', $result->nextNodeId);
        $this->assertCount(1, $this->workflowStarter->sequentialCalls);

        $call = $this->workflowStarter->sequentialCalls[0];
        $this->assertSame([self::ACTION_A, self::ACTION_B], $call->actionIds);

        $tpl = $call->context['tpl'] ?? null;
        $this->assertIsArray($tpl);
        $this->assertSame('a@example.com', $tpl['to'] ?? null);
        $this->assertSame((string) $this->run->id, $call->scenarioRunId);
        $this->assertSame([0, 60], $call->backoffByActionId[self::ACTION_A]);
        $this->assertSame(30, $call->delayBeforeByActionId[self::ACTION_A]);
        $this->assertSame([], $call->backoffByActionId[self::ACTION_B]);
        $this->assertSame(0, $call->delayBeforeByActionId[self::ACTION_B]);
    }

    public function test_advance_dispatches_parallel_batch(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'execution_mode' => 'parallel',
                'action_items' => [
                    ['code' => 'a', 'action_id' => self::ACTION_A, 'input' => []],
                    ['code' => 'b', 'action_id' => self::ACTION_B, 'input' => []],
                ],
            ],
        ];

        $this->handler->advance($this->run, $node);

        $this->assertCount(1, $this->workflowStarter->parallelCalls);
        $this->assertSame([self::ACTION_A, self::ACTION_B], $this->workflowStarter->parallelCalls[0]->actionIds);
    }

    public function test_advance_dispatches_before_and_on_error_hooks(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'execution_mode' => 'sequential',
                'action_items' => [
                    ['code' => 'main', 'action_id' => self::ACTION_A, 'input' => []],
                ],
                'before_code' => 'prep',
                'before_action_id' => self::ACTION_BEFORE,
                'before_input' => [],
                'error_code' => 'oops',
                'error_action_id' => self::ACTION_ERROR,
                'error_input' => [],
            ],
        ];

        $this->handler->advance($this->run, $node);

        $this->assertCount(1, $this->workflowStarter->sequentialCalls);
        $call = $this->workflowStarter->sequentialCalls[0];

        $this->assertSame([self::ACTION_BEFORE, self::ACTION_A], $call->actionIds);
        $this->assertSame([self::ACTION_ERROR], $call->onErrorActionIds);
    }

    public function test_advance_skips_dispatch_when_no_actions(): void
    {
        $node = ['id' => 'node_action', 'type' => 'action', 'data' => ['action_items' => []]];
        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_next', $result->nextNodeId);
        $this->assertCount(0, $this->workflowStarter->sequentialCalls);
    }

    public function test_continue_from_also_dispatches(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'action_items' => [
                    ['code' => 'a', 'action_id' => self::ACTION_A, 'input' => []],
                ],
            ],
        ];

        $next = $this->handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: null),
        );

        $this->assertSame('node_next', $next);
        $this->assertCount(1, $this->workflowStarter->sequentialCalls);
    }

    public function test_wait_for_result_dispatches_async_pipeline_and_pauses(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'wait_for_result' => true,
                'action_items' => [
                    ['code' => 'send_email', 'action_id' => self::ACTION_A, 'input' => []],
                ],
            ],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertNull($result->nextNodeId);
        $this->assertTrue($result->pause);

        $this->assertCount(1, $this->workflowStarter->sequentialCalls);
        $call = $this->workflowStarter->sequentialCalls[0];

        $this->assertSame('node_action', $call->scenarioNodeId);
        $this->assertSame('node_action', $call->context['scenario_node_id'] ?? null);

        $context = $this->run->fresh()->context;
        $this->assertSame('running', $context['_action_runs']['node_action'] ?? null);
        $this->assertArrayNotHasKey('send_email', $context);
    }

    public function test_async_path_does_not_write_result_to_context(): void
    {
        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'action_items' => [
                    ['code' => 'c_00a1', 'action_id' => self::ACTION_A, 'input' => []],
                ],
            ],
        ];

        $this->handler->advance($this->run, $node);

        $this->assertArrayNotHasKey('c_00a1', $this->run->fresh()->context ?? []);
    }
}
