<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\DTO\ActionRunIndexData;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionRun;
use Module\Actions\Services\ActionRunService;
use Tests\TestCase;

final class ActionRunServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_returns_failed_runs_with_reason_and_attempts_count(): void
    {
        $action = Action::query()->create([
            'name' => 'Failing action',
            'slug' => 'failing-action',
            'type' => 'template_file',
            'is_active' => true,
        ]);

        ActionRun::query()->create([
            'action_id' => $action->id,
            'status' => ActionRunStatus::Failed->value,
            'input' => ['lead_id' => 10],
            'error' => 'Template is invalid.',
            'attempts_count' => 3,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $items = app(ActionRunService::class)->items(
            new ActionRunIndexData(
                status: ActionRunStatus::Failed->value,
                actionId: null,
                limit: 100,
            )
        );

        $this->assertCount(1, $items);
        $this->assertSame('failed', $items[0]['status']);
        $this->assertSame('Template is invalid.', $items[0]['reason']);
        $this->assertSame(3, $items[0]['attempts_count']);
        $this->assertSame('Failing action', $items[0]['action_name']);
    }
}
