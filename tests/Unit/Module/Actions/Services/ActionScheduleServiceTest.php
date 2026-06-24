<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Module\Actions\Jobs\ChainStepJob;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\ActionScheduleService;
use Tests\TestCase;

final class ActionScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_due_dispatches_scheduled_action_and_updates_timestamps(): void
    {
        Bus::fake();

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
            'next_run_at' => now()->subMinute(),
        ]);

        $count = app(ActionScheduleService::class)->runDue();

        $this->assertSame(1, $count);
        Bus::assertDispatched(ChainStepJob::class);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->greaterThan($schedule->last_run_at));
    }

    public function test_upsert_disables_schedule_without_next_run(): void
    {
        $action = Action::query()->create([
            'name' => 'Disabled schedule action',
            'slug' => 'disabled-schedule-action',
            'code' => 'disabled_schedule_action',
            'type' => 'email',
            'is_active' => true,
        ]);

        $schedule = app(ActionScheduleService::class)->upsert(
            action: $action,
            enabled: false,
            cron: '30 9 * * *',
            timezone: 'UTC',
            input: [],
            options: [],
            settings: [],
        );

        $this->assertFalse($schedule->enabled);
        $this->assertNull($schedule->next_run_at);
    }
}
