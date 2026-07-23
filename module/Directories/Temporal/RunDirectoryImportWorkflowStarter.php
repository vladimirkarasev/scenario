<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

use Illuminate\Support\Str;
use Module\Directories\Temporal\Workflows\RunDirectoryImportPagedWorkflowInterface;
use Module\Directories\Temporal\Workflows\RunDirectoryImportWorkflowInterface;
use Module\Schedule\Support\TemporalTaskQueue;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

final readonly class RunDirectoryImportWorkflowStarter implements RunDirectoryImportWorkflowStarterInterface
{
    public function __construct(
        private WorkflowClientInterface $client,
        private TemporalTaskQueue $taskQueue,
    ) {}

    public function start(RunDirectoryImportWorkflowInput $input): void
    {
        $workflow = $this->client->newWorkflowStub(
            RunDirectoryImportWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('run-directory-import-'.Str::uuid()->toString())
                ->withTaskQueue($this->taskQueue->value()),
        );

        $this->client->start($workflow, $input->directoryImportId);
    }

    public function startPaged(RunDirectoryImportWorkflowInput $input): void
    {
        $workflow = $this->client->newWorkflowStub(
            RunDirectoryImportPagedWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('run-directory-import-paged-'.Str::uuid()->toString())
                ->withTaskQueue($this->taskQueue->value()),
        );

        $this->client->start($workflow, $input->directoryImportId);
    }
}
