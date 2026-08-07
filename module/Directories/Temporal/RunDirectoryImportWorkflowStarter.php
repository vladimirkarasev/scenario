<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Module\Directories\Temporal\Workflows\RunDirectoryImportPagedWorkflowInterface;
use Module\Schedule\Support\TemporalTaskQueue;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

final readonly class RunDirectoryImportWorkflowStarter implements RunDirectoryImportWorkflowStarterInterface
{
    public function __construct(
        private Container $container,
        private TemporalTaskQueue $taskQueue,
    ) {}

    public function start(RunDirectoryImportWorkflowInput $input): void
    {
        $client = $this->container->make(WorkflowClientInterface::class);
        $workflow = $client->newWorkflowStub(
            RunDirectoryImportPagedWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('run-directory-import-'.Str::uuid()->toString())
                ->withTaskQueue($this->taskQueue->value()),
        );

        $client->start($workflow, $input->directoryImportId);
    }
}
