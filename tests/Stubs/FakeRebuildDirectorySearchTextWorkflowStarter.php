<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;

final class FakeRebuildDirectorySearchTextWorkflowStarter implements RebuildDirectorySearchTextWorkflowStarterInterface
{
    /** @var list<int> */
    public array $calls = [];

    public function start(int $directoryVersionId): void
    {
        $this->calls[] = $directoryVersionId;
    }
}
