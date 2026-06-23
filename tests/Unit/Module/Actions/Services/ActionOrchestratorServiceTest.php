<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Jobs\ChainStepJob;
use Module\Actions\Jobs\DispatchActionBatchJob;
use Module\Actions\Services\ActionOrchestratorService;
use Tests\TestCase;

final class ActionOrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;
    private const TEMPLATE_ID = '00000000-0000-0000-0000-000000000010';
    private const EMAIL_ID = '00000000-0000-0000-0000-000000000011';
    private const SCENARIO_ID = '00000000-0000-0000-0000-000000000007';
    private const LOG_ID = '00000000-0000-0000-0000-000000000020';
    private const ALERT_ID = '00000000-0000-0000-0000-000000000030';

    public function test_sequential_dispatches_single_chain_step_with_full_pipeline(): void
    {
        Bus::fake();

        $data = new RunActionsData(
            mode: 'sequential',
            actions: ['email_template' => self::TEMPLATE_ID, 'lead_email' => self::EMAIL_ID],
            before: ['scenario' => self::SCENARIO_ID],
            after: ['log' => self::LOG_ID],
            onError: ['alert' => self::ALERT_ID],
            input: ['scenario_run_id' => '44'],
            schedule: null,
            canManageActions: true,
        );

        $result = app(ActionOrchestratorService::class)->runFromData($data);

        $this->assertSame('queued', $result['status']);
        $this->assertSame(4, $result['queued']);
        Bus::assertDispatched(ChainStepJob::class);
    }

    public function test_parallel_dispatches_batch_job(): void
    {
        Bus::fake();

        $data = new RunActionsData(
            mode: 'parallel',
            actions: ['email_template' => self::TEMPLATE_ID, 'lead_email' => self::EMAIL_ID],
            before: [],
            after: [],
            onError: [],
            input: ['scenario_run_id' => '44'],
            schedule: null,
            canManageActions: true,
        );

        $result = app(ActionOrchestratorService::class)->runFromData($data);

        $this->assertSame('queued', $result['status']);
        $this->assertSame(2, $result['queued']);
        Bus::assertDispatched(DispatchActionBatchJob::class);
    }
}
