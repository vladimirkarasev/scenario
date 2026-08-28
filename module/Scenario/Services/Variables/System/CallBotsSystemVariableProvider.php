<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioType;

final readonly class CallBotsSystemVariableProvider implements ScenarioSystemVariableProvider
{
    public function __construct(private CallSystemVariableProvider $calls)
    {
    }

    public function supports(ScenarioType $type): bool
    {
        return $type === ScenarioType::CallBots;
    }

    /** @return iterable<SystemVariableGroup> */
    public function groups(): iterable
    {
        yield from $this->calls->groups();
    }
}
