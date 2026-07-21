<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionScheduleService;
use Module\Actions\Temporal\ActionScheduleSyncerInterface;
use Tests\Stubs\FakeActionScheduleSyncer;
use Tests\TestCase;

final class ActionScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeActionScheduleSyncer $syncer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncer = new FakeActionScheduleSyncer();
        $this->app->instance(ActionScheduleSyncerInterface::class, $this->syncer);
    }

    public function test_upsert_syncs_temporal_schedule(): void
    {
        $action = Action::query()->create([
            'name' => 'Scheduled action',
            'slug' => 'scheduled-action',
            'code' => 'scheduled_action',
            'type' => 'email',
            'is_active' => true,
        ]);

        $schedule = app(ActionScheduleService::class)->upsert(
            action: $action,
            enabled: true,
            cron: '30 9 * * *',
            timezone: 'UTC',
            input: ['lead_id' => 15],
            options: [],
            settings: [],
        );

        $this->assertCount(1, $this->syncer->syncedSchedules);
        $synced = $this->syncer->syncedSchedules[0];
        $this->assertSame($schedule->id, $synced->id);
        $this->assertTrue($synced->enabled);
        $this->assertSame('30 9 * * *', $synced->cron);
        $this->assertSame('UTC', $synced->timezone);

        $this->assertNotNull($schedule->next_run_at);
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

        $this->assertCount(1, $this->syncer->syncedSchedules);
        $this->assertFalse($this->syncer->syncedSchedules[0]->enabled);
    }

    public function test_delete_removes_schedule_and_temporal_schedule(): void
    {
        $action = Action::query()->create([
            'name' => 'Deletable schedule action',
            'slug' => 'deletable-schedule-action',
            'code' => 'deletable_schedule_action',
            'type' => 'email',
            'is_active' => true,
        ]);

        $schedule = app(ActionScheduleService::class)->upsert(
            action: $action,
            enabled: true,
            cron: '30 9 * * *',
            timezone: 'UTC',
            input: [],
            options: [],
            settings: [],
        );
        $scheduleId = $schedule->id;

        app(ActionScheduleService::class)->delete($schedule);

        $this->assertNull($action->schedule()->first());
        $this->assertContains($scheduleId, $this->syncer->deletedScheduleIds);
    }
}
