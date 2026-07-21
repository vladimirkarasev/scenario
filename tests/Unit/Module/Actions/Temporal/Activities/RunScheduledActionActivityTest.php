<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Temporal\Activities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Temporal\Activities\RunScheduledActionActivity;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;
use Tests\Stubs\FakeRunActionsWorkflowStarter;
use Tests\TestCase;

final class RunScheduledActionActivityTest extends TestCase
{
    use RefreshDatabase;

    private FakeRunActionsWorkflowStarter $starter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->starter = new FakeRunActionsWorkflowStarter();
        $this->app->instance(RunActionsWorkflowStarterInterface::class, $this->starter);
    }

    public function test_run_dispatches_action_and_updates_timestamps(): void
    {
        $action = Action::query()->create([
            'name' => 'Scheduled action',
            'slug' => 'scheduled-action',
            'code' => 'scheduled_action',
            'type' => 'email',
            'is_active' => true,
        ]);

        $schedule = ActionSchedule::query()->create([
            'action_id' => $action->id,
            'enabled' => true,
            'cron' => '30 9 * * *',
            'timezone' => 'UTC',
            'input' => ['lead_id' => 15],
            'options' => [],
        ]);

        app(RunScheduledActionActivity::class)->run($schedule->id);

        $this->assertCount(1, $this->starter->sequentialCalls);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->greaterThan($schedule->last_run_at));
    }

    public function test_run_skips_dispatch_but_updates_timestamps_when_action_inactive(): void
    {
        $action = Action::query()->create([
            'name' => 'Inactive action',
            'slug' => 'inactive-action',
            'code' => 'inactive_action',
            'type' => 'email',
            'is_active' => false,
        ]);

        $schedule = ActionSchedule::query()->create([
            'action_id' => $action->id,
            'enabled' => true,
            'cron' => '30 9 * * *',
            'timezone' => 'UTC',
            'input' => [],
            'options' => [],
        ]);

        app(RunScheduledActionActivity::class)->run($schedule->id);

        $this->assertCount(0, $this->starter->sequentialCalls);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_run_does_nothing_when_disabled(): void
    {
        $action = Action::query()->create([
            'name' => 'Disabled schedule action',
            'slug' => 'disabled-schedule-action-2',
            'code' => 'disabled_schedule_action_2',
            'type' => 'email',
            'is_active' => true,
        ]);

        $schedule = ActionSchedule::query()->create([
            'action_id' => $action->id,
            'enabled' => false,
            'cron' => '30 9 * * *',
            'timezone' => 'UTC',
            'input' => [],
            'options' => [],
        ]);

        app(RunScheduledActionActivity::class)->run($schedule->id);

        $this->assertCount(0, $this->starter->sequentialCalls);

        $schedule->refresh();
        $this->assertNull($schedule->last_run_at);
        $this->assertNull($schedule->next_run_at);
    }
}
