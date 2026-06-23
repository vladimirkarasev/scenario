<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Module\Actions\Jobs\ChainStepJob;
use Module\Actions\Jobs\DispatchActionBatchJob;
use Module\Actions\Models\Action;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Action\ActionNodeHandler;
use Tests\TestCase;

final class ActionNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private const ACTION_A = '00000000-0000-0000-0000-0000000000a1';

    private const ACTION_B = '00000000-0000-0000-0000-0000000000a2';

    private const ACTION_BEFORE = '00000000-0000-0000-0000-0000000000b1';

    private const ACTION_ERROR = '00000000-0000-0000-0000-0000000000e1';

    private ActionNodeHandler $handler;

    private ScenarioVersion $version;

    private ScenarioRun $run;

    protected function setUp(): void
    {
        parent::setUp();

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
                'key' => 'k_'.substr($id, -4),
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

    public function test_advance_dispatches_sequential_chain_for_action_items(): void
    {
        Bus::fake();

        $node = [
            'id' => 'node_action',
            'type' => 'action',
            'data' => [
                'skipInSurvey' => true,
                'execution_mode' => 'sequential',
                'action_items' => [
                    ['code' => 'tpl', 'action_id' => self::ACTION_A, 'input' => ['to' => '{{ user.email }}']],
                    ['code' => 'send', 'action_id' => self::ACTION_B, 'input' => []],
                ],
            ],
        ];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_next', $result->nextNodeId);
        Bus::assertDispatched(ChainStepJob::class, function (ChainStepJob $job) {
            $reflection = new \ReflectionClass($job);
            $actionId = $reflection->getProperty('actionId')->getValue($job);
            $context = $reflection->getProperty('context')->getValue($job);

            if ($actionId !== self::ACTION_A || !is_array($context)) {
                return false;
            }

            $tpl = $context['tpl'] ?? null;

            return is_array($tpl)
                && ($tpl['to'] ?? null) === 'a@example.com'
                && ($context['scenario_run_id'] ?? null) === (string)$this->run->id;
        });
    }

    public function test_advance_dispatches_parallel_batch(): void
    {
        Bus::fake();

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

        Bus::assertDispatched(DispatchActionBatchJob::class);
    }

    public function test_advance_dispatches_before_and_on_error_hooks(): void
    {
        Bus::fake();

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

        Bus::assertDispatched(ChainStepJob::class, function (ChainStepJob $job) {
            $reflection = new \ReflectionClass($job);
            $actionId = $reflection->getProperty('actionId')->getValue($job);
            $remaining = $reflection->getProperty('remainingActionIds')->getValue($job);
            $failed = $reflection->getProperty('failedActionIds')->getValue($job);

            return $actionId === self::ACTION_BEFORE
                && $remaining === [self::ACTION_A]
                && $failed === [self::ACTION_ERROR];
        });
    }

    public function test_advance_skips_dispatch_when_no_actions(): void
    {
        Bus::fake();

        $node = ['id' => 'node_action', 'type' => 'action', 'data' => ['action_items' => []]];
        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_next', $result->nextNodeId);
        Bus::assertNothingDispatched();
    }

    public function test_continue_from_also_dispatches(): void
    {
        Bus::fake();

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
        Bus::assertDispatched(ChainStepJob::class);
    }

    public function test_wait_for_result_dispatches_async_pipeline_and_pauses(): void
    {
        Bus::fake();

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

        // Прогон паузится на ноде (ждём завершения цепочки по WS), не продвигается.
        $this->assertNull($result->nextNodeId);
        $this->assertTrue($result->pause);

        // Цепочка задиспатчена с scenario_node_id — это включает pipeline по WS и авто-резюм.
        Bus::assertDispatched(ChainStepJob::class, function (ChainStepJob $job): bool {
            $reflection = new \ReflectionClass($job);
            $nodeId = $reflection->getProperty('scenarioNodeId')->getValue($job);
            $context = $reflection->getProperty('context')->getValue($job);

            return $nodeId === 'node_action'
                && is_array($context)
                && ($context['scenario_node_id'] ?? null) === 'node_action';
        });

        // Нода помечена running, синхронно результат НЕ записан.
        $context = $this->run->fresh()->context;
        $this->assertSame('running', $context['_action_runs']['node_action'] ?? null);
        $this->assertArrayNotHasKey('send_email', $context);
    }

    public function test_async_path_does_not_write_result_to_context(): void
    {
        Bus::fake();

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
