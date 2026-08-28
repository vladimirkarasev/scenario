<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioType;

final readonly class WhatsappSystemVariableProvider implements ScenarioSystemVariableProvider
{
    public function supports(ScenarioType $type): bool
    {
        return $type === ScenarioType::Watsapp;
    }

    public function groups(): iterable
    {
        yield from [];
    }
}
