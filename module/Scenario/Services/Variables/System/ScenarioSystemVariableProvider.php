<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioType;

interface ScenarioSystemVariableProvider
{
    public function supports(ScenarioType $type): bool;

    /** @return iterable<SystemVariableGroup> */
    public function groups(): iterable;
}
