<?php

declare(strict_types=1);

namespace Module\Actions\Enums;

enum ActionRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
