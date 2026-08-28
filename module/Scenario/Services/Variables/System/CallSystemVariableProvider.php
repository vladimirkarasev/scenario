<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Enums\ScenarioType;

final readonly class CallSystemVariableProvider implements ScenarioSystemVariableProvider
{
    public function supports(ScenarioType $type): bool
    {
        return $type === ScenarioType::Colls;
    }

    /** @return iterable<SystemVariableGroup> */
    public function groups(): iterable
    {
        yield new SystemVariableGroup(ScenarioContextKey::Call, [
            new SystemVariableField('incoming_phone', 'Входящий номер телефона', 'Номер входящего звонка'),
            new SystemVariableField('outgoing_phone', 'Исходящий номер телефона', 'Номер исходящего звонка'),
            new SystemVariableField('internal_phone', 'Внутренний номер', 'Внутренний номер сотрудника'),
            new SystemVariableField('id', 'Идентификатор звонка', 'Уникальный идентификатор звонка'),
        ]);
    }
}
