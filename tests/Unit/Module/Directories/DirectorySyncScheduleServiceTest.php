<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectorySyncScheduleService;
use Module\Projects\Models\Project;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Tests\Stubs\FakeTemporalScheduleSyncer;
use Tests\TestCase;

final class DirectorySyncScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeTemporalScheduleSyncer $syncer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncer = new FakeTemporalScheduleSyncer();
        $this->app->instance(TemporalScheduleSyncerInterface::class, $this->syncer);
    }

    public function test_upsert_syncs_temporal_schedule(): void
    {
        $directory = $this->makeDirectory();

        $schedule = app(DirectorySyncScheduleService::class)->upsert(
            directory: $directory,
            enabled: true,
            cron: '0 */6 * * *',
            timezone: 'UTC',
        );

        $this->assertCount(1, $this->syncer->upserts);
        $synced = $this->syncer->upserts[0];
        $this->assertSame("directory-sync-{$directory->id}", $synced['scheduleId']);
        $this->assertSame('0 */6 * * *', $synced['cron']);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_upsert_disables_schedule_without_next_run(): void
    {
        $directory = $this->makeDirectory();

        $schedule = app(DirectorySyncScheduleService::class)->upsert(
            directory: $directory,
            enabled: false,
            cron: null,
            timezone: null,
        );

        $this->assertNull($schedule->next_run_at);
        $this->assertCount(1, $this->syncer->deletedScheduleIds);
    }

    public function test_disable_removes_schedule_and_temporal_schedule(): void
    {
        $directory = $this->makeDirectory();
        $service = app(DirectorySyncScheduleService::class);

        $service->upsert($directory, enabled: true, cron: '0 * * * *', timezone: 'UTC');
        $service->disable($directory);

        $this->assertNull($service->findForDirectory($directory));
        $this->assertContains("directory-sync-{$directory->id}", $this->syncer->deletedScheduleIds);
    }

    private function makeDirectory(): Directory
    {
        $project = Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => 'api',
        ]);
    }
}
