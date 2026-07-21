<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Services\ActionOrchestratorService;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;
use Tests\Stubs\FakeRunActionsWorkflowStarter;
use Tests\TestCase;

final class ActionOrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string TEMPLATE_ID = '00000000-0000-0000-0000-000000000010';

    private const string EMAIL_ID = '00000000-0000-0000-0000-000000000011';

    private const string SCENARIO_ID = '00000000-0000-0000-0000-000000000007';

    private const string LOG_ID = '00000000-0000-0000-0000-000000000020';

    private const string ALERT_ID = '00000000-0000-0000-0000-000000000030';

    public function test_sequential_starts_run_actions_workflow_with_full_pipeline(): void
    {
        $starter = new FakeRunActionsWorkflowStarter();
        $this->app->instance(RunActionsWorkflowStarterInterface::class, $starter);

        $data = new RunActionsData(
            mode: 'sequential',
            actions: ['email_template' => self::TEMPLATE_ID, 'lead_email' => self::EMAIL_ID],
            before: ['scenario' => self::SCENARIO_ID],
            after: ['log' => self::LOG_ID],
            onError: ['alert' => self::ALERT_ID],
            input: ['scenario_run_id' => '44'],
            schedule: null,
            canManageActions: true,
            backoffMap: ['email_template' => [0, 60]],
            delayBeforeMap: ['scenario' => 15],
        );

        $result = app(ActionOrchestratorService::class)->runFromData($data);

        $this->assertSame('queued', $result['status']);
        $this->assertSame(4, $result['queued']);
        $this->assertCount(1, $starter->sequentialCalls);
        $call = $starter->sequentialCalls[0];
        $this->assertSame(
            [self::SCENARIO_ID, self::TEMPLATE_ID, self::EMAIL_ID, self::LOG_ID],
            $call->actionIds,
        );
        $this->assertSame([self::ALERT_ID], $call->onErrorActionIds);
        $this->assertSame('44', $call->scenarioRunId);
        $this->assertSame([0, 60], $call->backoffByActionId[self::TEMPLATE_ID]);
        $this->assertSame([], $call->backoffByActionId[self::EMAIL_ID]);
        $this->assertSame(15, $call->delayBeforeByActionId[self::SCENARIO_ID]);
        $this->assertSame(0, $call->delayBeforeByActionId[self::EMAIL_ID]);
    }

    public function test_parallel_starts_run_actions_parallel_workflow(): void
    {
        $starter = new FakeRunActionsWorkflowStarter();
        $this->app->instance(RunActionsWorkflowStarterInterface::class, $starter);

        $data = new RunActionsData(
            mode: 'parallel',
            actions: ['email_template' => self::TEMPLATE_ID, 'lead_email' => self::EMAIL_ID],
            before: ['scenario' => self::SCENARIO_ID],
            after: ['log' => self::LOG_ID],
            onError: ['alert' => self::ALERT_ID],
            input: ['scenario_run_id' => '44'],
            schedule: null,
            canManageActions: true,
            backoffMap: ['lead_email' => [0, 30, 90]],
            delayBeforeMap: ['email_template' => 5],
        );

        $result = app(ActionOrchestratorService::class)->runFromData($data);

        $this->assertSame('queued', $result['status']);
        $this->assertSame(3, $result['queued']);
        $this->assertCount(1, $starter->parallelCalls);

        $call = $starter->parallelCalls[0];
        $this->assertSame([self::SCENARIO_ID], $call->beforeIds);
        $this->assertSame([self::TEMPLATE_ID, self::EMAIL_ID], $call->actionIds);
        $this->assertSame([self::LOG_ID], $call->afterIds);
        $this->assertSame([self::ALERT_ID], $call->onErrorActionIds);
        $this->assertSame('44', $call->scenarioRunId);
        $this->assertSame([0, 30, 90], $call->backoffByActionId[self::EMAIL_ID]);
        $this->assertSame(5, $call->delayBeforeByActionId[self::TEMPLATE_ID]);
        $this->assertSame(0, $call->delayBeforeByActionId[self::EMAIL_ID]);
    }
}
