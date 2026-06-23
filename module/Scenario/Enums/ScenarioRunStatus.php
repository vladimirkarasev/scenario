<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioRunStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
}
