<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

use Illuminate\Support\Str;
use Module\Actions\Temporal\Workflows\RunActionsParallelWorkflowInterface;
use Module\Actions\Temporal\Workflows\RunActionsWorkflowInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

final readonly class RunActionsWorkflowStarter implements RunActionsWorkflowStarterInterface
{
    public function __construct(
        private WorkflowClientInterface $client,
    ) {}

    public function startSequential(RunActionsWorkflowInput $input): void
    {
        $workflow = $this->client->newWorkflowStub(
            RunActionsWorkflowInterface::class,
            WorkflowOptions::new()->withWorkflowId('run-actions-'.Str::uuid()->toString()),
        );

        $this->client->start(
            $workflow,
            $input->actionIds,
            $input->onErrorActionIds,
            $input->codeMap,
            $input->context,
            $input->scopeMap,
            $input->backoffByActionId,
            $input->delayBeforeByActionId,
            $input->scenarioRunId,
            $input->scenarioNodeId,
            $input->actionNodeId,
        );
    }

    public function startParallel(RunActionsParallelWorkflowInput $input): void
    {
        $workflow = $this->client->newWorkflowStub(
            RunActionsParallelWorkflowInterface::class,
            WorkflowOptions::new()->withWorkflowId('run-actions-parallel-'.Str::uuid()->toString()),
        );

        $this->client->start(
            $workflow,
            $input->beforeIds,
            $input->actionIds,
            $input->afterIds,
            $input->onErrorActionIds,
            $input->codeMap,
            $input->context,
            $input->backoffByActionId,
            $input->delayBeforeByActionId,
            $input->scenarioRunId,
            $input->actionNodeId,
        );
    }
}
