<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\ScenarioRunResultActionHandler;
use Module\Scenario\Services\Runtime\ScenarioPlayerService;
use Tests\TestCase;

final class ScenarioRunResultActionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_fails_when_scenario_uuid_not_provided(): void
    {
        $handler = new ScenarioRunResultActionHandler(
            app(ActionDataResolver::class),
            app(ScenarioPlayerService::class),
        );

        $result = $handler->handle(new Action(['slug' => 'scenario_data', 'config' => []]), []);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $this->assertStringContainsString('scenario_uuid', $result->error ?? '');
    }

    public function test_handle_fails_when_scenario_uuid_does_not_exist(): void
    {
        $handler = new ScenarioRunResultActionHandler(
            app(ActionDataResolver::class),
            app(ScenarioPlayerService::class),
        );

        $action = new Action([
            'slug' => 'scenario_data',
            'config' => ['scenario_uuid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $result = $handler->handle($action, []);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $this->assertStringContainsString('not found', $result->error ?? '');
    }

    public function test_handle_reads_scenario_uuid_from_scoped_input_as_fallback(): void
    {
        $handler = new ScenarioRunResultActionHandler(
            app(ActionDataResolver::class),
            app(ScenarioPlayerService::class),
        );

        $action = new Action(['slug' => 'scenario_data', 'code' => 'scenario_data', 'config' => []]);

        $result = $handler->handle($action, [
            'scenario_data' => ['scenario_uuid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $this->assertStringContainsString('not found', $result->error ?? '');
    }
}
