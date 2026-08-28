<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

enum ActionPipelineStage
{
    case Action;
    case Before;
    case OnError;
}
