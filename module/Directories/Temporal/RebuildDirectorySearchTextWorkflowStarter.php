<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Module\Directories\Temporal\Workflows\RebuildDirectorySearchTextWorkflowInterface;
use Module\Schedule\Support\TemporalTaskQueue;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

final readonly class RebuildDirectorySearchTextWorkflowStarter implements RebuildDirectorySearchTextWorkflowStarterInterface
{
    public function __construct(
        private Container $container,
        private TemporalTaskQueue $taskQueue,
    ) {
    }

    public function start(int $directoryVersionId): void
    {
        $client = $this->container->make(WorkflowClientInterface::class);
        $workflow = $client->newWorkflowStub(
            RebuildDirectorySearchTextWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('rebuild-directory-search-text-'.$directoryVersionId.'-'.Str::uuid()->toString())
                ->withTaskQueue($this->taskQueue->value()),
        );

        $client->start($workflow, $directoryVersionId);
    }
}
