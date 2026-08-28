<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use LogicException;
use Module\Scenario\Enums\ScenarioType;

final readonly class ScenarioSystemVariableRegistry
{
    public function __construct(
        private CallSystemVariableProvider $call,
        private CallBotsSystemVariableProvider $callBots,
        private TelegramSystemVariableProvider $telegram,
        private WhatsappSystemVariableProvider $whatsapp,
    ) {
    }

    public function for(ScenarioType $type): ScenarioSystemVariableProvider
    {
        foreach ($this->providers() as $provider) {
            if ($provider->supports($type)) {
                return $provider;
            }
        }

        throw new LogicException(sprintf('System variable provider for scenario type "%s" is not registered.', $type->value));
    }

    /** @return iterable<ScenarioSystemVariableProvider> */
    private function providers(): iterable
    {
        yield $this->call;
        yield $this->callBots;
        yield $this->telegram;
        yield $this->whatsapp;
    }
}
