<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Temporal\Activities;

use App\Events\CentrifugoMessagePublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Events\ScenarioActionStageFinished;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionRun;
use Module\Actions\Temporal\Activities\ExecuteActionActivity;
use Temporal\Exception\Failure\ApplicationFailure;
use Tests\TestCase;

final class ExecuteActionActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_output_and_publishes_ws_events(): void
    {
        Storage::fake('local');
        Event::fake();

        $action = Action::query()->create([
            'name' => 'Template',
            'slug' => 'template',
            'code' => 'template',
            'type' => 'template_file',
            'is_active' => true,
            'config' => [
                'format' => 'txt',
                'template' => 'hello',
                'file_name' => 'greeting.txt',
            ],
        ]);

        $result = app(ExecuteActionActivity::class)->execute($action->id, [], 'template', 'run-1', 1, 'node-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('greeting.txt', $result['output']['file_name'] ?? null);
        $this->assertNull($result['error']);

        Event::assertDispatched(
            CentrifugoMessagePublished::class,
            fn (CentrifugoMessagePublished $event) => $event->channel === 'scenario-run:run-1'
                && $event->payload['type'] === 'action_started',
        );
        Event::assertDispatched(
            CentrifugoMessagePublished::class,
            fn (CentrifugoMessagePublished $event) => $event->channel === 'scenario-run:run-1'
                && $event->payload['type'] === 'action_completed',
        );
        Event::assertDispatched(
            ScenarioActionStageFinished::class,
            fn (ScenarioActionStageFinished $event) => $event->scenarioRunId === 'run-1'
                && $event->actionNodeId === 'node-1'
                && $event->status === ActionRunStatus::Success,
        );
    }

    public function test_execute_without_action_node_id_does_not_dispatch_scenario_action_stage_finished(): void
    {
        Storage::fake('local');
        Event::fake();

        $action = Action::query()->create([
            'name' => 'Template',
            'slug' => 'template',
            'code' => 'template',
            'type' => 'template_file',
            'is_active' => true,
            'config' => [
                'format' => 'txt',
                'template' => 'hello',
                'file_name' => 'greeting.txt',
            ],
        ]);

        app(ExecuteActionActivity::class)->execute($action->id, [], 'template', 'run-1');

        Event::assertNotDispatched(ScenarioActionStageFinished::class);
    }

    public function test_execute_passes_attempt_number_to_action_run(): void
    {
        Storage::fake('local');

        $action = Action::query()->create([
            'name' => 'Template',
            'slug' => 'template',
            'code' => 'template',
            'type' => 'template_file',
            'is_active' => true,
            'config' => [
                'format' => 'txt',
                'template' => 'hello',
                'file_name' => 'greeting.txt',
            ],
        ]);

        app(ExecuteActionActivity::class)->execute($action->id, [], 'template', null, 3);

        $run = ActionRun::query()->where('action_id', $action->id)->firstOrFail();
        $this->assertSame(3, $run->attempts_count);
    }

    public function test_execute_without_scenario_run_id_publishes_nothing(): void
    {
        Storage::fake('local');
        Event::fake();

        $action = Action::query()->create([
            'name' => 'Template',
            'slug' => 'template',
            'code' => 'template',
            'type' => 'template_file',
            'is_active' => true,
            'config' => [
                'format' => 'txt',
                'template' => 'hello',
                'file_name' => 'greeting.txt',
            ],
        ]);

        app(ExecuteActionActivity::class)->execute($action->id, [], 'template', null);

        Event::assertNotDispatched(CentrifugoMessagePublished::class);
    }

    public function test_execute_throws_application_failure_with_clean_message_on_failure(): void
    {
        Event::fake();

        $action = Action::query()->create([
            'name' => 'Template',
            'slug' => 'template',
            'code' => 'template',
            'type' => 'template_file',
            'is_active' => true,
            'config' => [
                'format' => 'txt',
            ],
        ]);

        try {
            app(ExecuteActionActivity::class)->execute($action->id, [], 'template', 'run-1', 1, 'node-1');
            $this->fail('Expected ApplicationFailure was not thrown.');
        } catch (ApplicationFailure $exception) {
            $this->assertSame(
                'Template action requires `template` in config.',
                $exception->getDetails()->getValue(0, 'string'),
            );
        }

        Event::assertDispatched(
            CentrifugoMessagePublished::class,
            fn (CentrifugoMessagePublished $event) => $event->payload['type'] === 'action_failed',
        );
        Event::assertDispatched(
            ScenarioActionStageFinished::class,
            fn (ScenarioActionStageFinished $event) => $event->status === ActionRunStatus::Failed
                && $event->error === 'Template action requires `template` in config.',
        );
    }
}
