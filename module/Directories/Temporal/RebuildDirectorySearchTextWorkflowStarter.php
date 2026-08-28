<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

use Illuminate\Support\Str;
use Module\Directories\Temporal\Workflows\RebuildDirectorySearchTextWorkflowInterface;
use Module\Schedule\Support\TemporalTaskQueue;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

final readonly class RebuildDirectorySearchTextWorkflowStarter implements RebuildDirectorySearchTextWorkflowStarterInterface
{
    public function __construct(
        private WorkflowClientInterface $client,
        private TemporalTaskQueue $taskQueue,
    ) {
    }

    public function start(int $directoryVersionId): void
    {
        $workflow = $this->client->newWorkflowStub(
            RebuildDirectorySearchTextWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('rebuild-directory-search-text-'.$directoryVersionId.'-'.Str::uuid()->toString())
                ->withTaskQueue($this->taskQueue->value()),
        );

        $this->client->start($workflow, $directoryVersionId);
    }
}
