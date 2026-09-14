<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Events\ScenarioActionStageFinished;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Enums\ScenarioRunHistoryEventType;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunHistoryEvent;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Condition\ConditionNodeHandler;
use Module\Scenario\Services\Nodes\ScenarioLink\ScenarioLinkNodeHandler;
use Module\Scenario\Services\Runtime\ScenarioRunHistoryService;
use Module\Scenario\Services\Runtime\ScenarioRunStepManager;
use Tests\TestCase;

/**
 * Проверяет, что доменные события пишут строки в scenario_run_history_events
 * и что ScenarioRunHistoryService корректно их читает — без прогона через
 * ScenarioPlayerService (его граф узлов тянет за собой Temporal-клиент,
 * которому в тестовом окружении нужен недоступный здесь расширение grpc).
 */
final class ScenarioRunHistoryEventsTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioRun $run;

    private ScenarioVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $scenario = Scenario::query()->create(['name' => 'History test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision($this->version, [
            'nodes_json' => [
                ['id' => 'node_block', 'type' => 'block', 'data' => [
                    'title' => 'Форма',
                    'fields' => [
                        ['id' => 'price', 'name' => 'price', 'label' => 'Цена'],
                    ],
                ]],
                ['id' => 'node_condition', 'type' => 'condition', 'data' => []],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_block', 'target' => 'node_condition'],
                ['id' => 'e2', 'source' => 'node_condition', 'target' => 'node_end'],
            ],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $this->version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_block',
            'status' => 'active',
            'context' => [],
        ]);
    }

    public function test_ensure_open_and_close_open_record_transition_and_field_filled(): void
    {
        $manager = app(ScenarioRunStepManager::class);
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => ['fields' => [['id' => 'price', 'name' => 'price', 'label' => 'Цена']]],
        ];

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 150], ['next_node_id' => 'node_condition']);

        $types = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ScenarioRunHistoryEvent $e): string => $e->type->value)
            ->all();

        $this->assertSame(['transition', 'field_filled'], $types);

        $fieldEvent = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::FieldFilled->value)
            ->firstOrFail();

        $this->assertSame('price', $fieldEvent->payload['field_id']);
        $this->assertSame('Цена', $fieldEvent->payload['field_label']);
        $this->assertSame(150, $fieldEvent->payload['new_value']);
        $this->assertNull($fieldEvent->payload['old_value']);
    }

    public function test_second_visit_with_different_value_records_field_changed(): void
    {
        $manager = app(ScenarioRunStepManager::class);
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => ['fields' => [['id' => 'price', 'name' => 'price', 'label' => 'Цена']]],
        ];

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 100], []);

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 200], []);

        $changed = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::FieldChanged->value)
            ->firstOrFail();

        $this->assertSame(100, $changed->payload['old_value']);
        $this->assertSame(200, $changed->payload['new_value']);
    }

    public function test_second_visit_with_same_value_records_nothing_new(): void
    {
        $manager = app(ScenarioRunStepManager::class);
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => ['fields' => [['id' => 'price', 'name' => 'price', 'label' => 'Цена']]],
        ];

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 100], []);

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 100], []);

        $fieldEventsCount = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->whereIn('type', [
                ScenarioRunHistoryEventType::FieldFilled->value,
                ScenarioRunHistoryEventType::FieldChanged->value,
            ])
            ->count();

        $this->assertSame(1, $fieldEventsCount);
    }

    public function test_condition_auto_mode_records_condition_evaluated(): void
    {
        $handler = app(ConditionNodeHandler::class);
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'auto',
                'expression' => '1',
                'rules' => [['operator' => 'equals', 'value' => '1', 'targetNodeId' => 'node_end']],
                'fallbackTargetNodeId' => 'node_end',
            ],
        ];

        $handler->advance($this->run, $node);

        $event = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::ConditionEvaluated->value)
            ->firstOrFail();

        $this->assertSame('node_condition', $event->node_id);
        $this->assertSame('auto', $event->payload['mode']);
        $this->assertSame('node_end', $event->payload['target_node_id']);
    }

    public function test_condition_manual_mode_records_selected_label(): void
    {
        $handler = app(ConditionNodeHandler::class);
        $node = [
            'id' => 'node_condition',
            'type' => 'condition',
            'data' => [
                'mode' => 'manual',
                'options' => [
                    ['label' => 'Вправо', 'condition' => 'true', 'targetNodeId' => 'node_end'],
                ],
            ],
        ];

        $handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: 'node_end'),
        );

        $event = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::ConditionEvaluated->value)
            ->firstOrFail();

        $this->assertSame('manual', $event->payload['mode']);
        $this->assertSame('Вправо', $event->payload['label']);
    }

    public function test_scenario_link_advance_records_scenario_link_followed(): void
    {
        $target = Scenario::query()->create(['name' => 'Target', 'is_active' => true]);
        $targetVersion = ScenarioVersion::query()->create(['scenario_id' => $target->id]);
        $this->createRevision($targetVersion, [
            'nodes_json' => [['id' => 'target_start', 'type' => 'start', 'data' => []]],
            'edges_json' => [],
        ]);

        $node = [
            'id' => 'node_link',
            'type' => 'scenario_link',
            'data' => ['targetScenarioId' => $target->id, 'targetVersionId' => $targetVersion->id],
        ];

        app(ScenarioLinkNodeHandler::class)->advance($this->run, $node);

        $event = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::ScenarioLinkFollowed->value)
            ->firstOrFail();

        $this->assertSame('node_link', $event->node_id);
        $this->assertSame($target->id, $event->payload['target_scenario_id']);
        $this->assertSame('Target', $event->payload['target_scenario_name']);
        $this->assertSame('target_start', $event->payload['start_node_id']);
    }

    public function test_scenario_action_stage_finished_event_is_recorded_as_action_completed(): void
    {
        Event::dispatch(new ScenarioActionStageFinished(
            scenarioRunId: (string) $this->run->id,
            actionNodeId: 'action_node',
            actionId: 'action-uuid',
            actionName: 'Send email',
            code: 'action_items',
            status: ActionRunStatus::Success,
            input: ['to' => 'a@b.c'],
            output: ['sent' => true],
            error: null,
        ));

        $event = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::ActionCompleted->value)
            ->firstOrFail();

        $this->assertSame('action_node', $event->node_id);
        $this->assertSame('Send email', $event->payload['action_name']);
        $this->assertSame(['sent' => true], $event->payload['output']);
    }

    public function test_scenario_action_stage_finished_event_with_failed_status_is_recorded_as_action_failed(): void
    {
        Event::dispatch(new ScenarioActionStageFinished(
            scenarioRunId: (string) $this->run->id,
            actionNodeId: 'action_node',
            actionId: 'action-uuid',
            actionName: 'Send email',
            code: 'action_items',
            status: ActionRunStatus::Failed,
            input: [],
            output: null,
            error: 'SMTP timeout',
        ));

        $event = ScenarioRunHistoryEvent::query()
            ->where('run_id', $this->run->id)
            ->where('type', ScenarioRunHistoryEventType::ActionFailed->value)
            ->firstOrFail();

        $this->assertSame('SMTP timeout', $event->payload['error']);
    }

    public function test_build_history_maps_events_in_order_with_expected_shape(): void
    {
        $manager = app(ScenarioRunStepManager::class);
        $node = [
            'id' => 'node_block',
            'type' => 'block',
            'data' => ['title' => 'Форма', 'fields' => [['id' => 'price', 'name' => 'price', 'label' => 'Цена']]],
        ];

        $manager->ensureOpen($this->run, $node);
        $manager->closeOpen($this->run, ['price' => 150], ['next_node_id' => 'node_condition']);

        $history = app(ScenarioRunHistoryService::class)->buildHistory($this->run);

        $this->assertCount(2, $history);
        $this->assertSame('transition', $history[0]['type']);
        $this->assertSame('Форма', $history[0]['node_title']);
        $this->assertSame('field_filled', $history[1]['type']);
        $this->assertSame('price', $history[1]['field_id']);
        $this->assertSame(150, $history[1]['new_value']);
    }
}
