<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

final readonly class RunDirectoryImportWorkflowInput
{
    public function __construct(
        public int $directoryImportId,
    ) {}
}
