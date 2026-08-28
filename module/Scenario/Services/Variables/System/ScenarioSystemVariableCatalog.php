<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioType;

final readonly class ScenarioSystemVariableCatalog
{
    public function __construct(
        private CommonSystemVariableProvider $common,
        private ScenarioSystemVariableRegistry $registry,
    ) {
    }

    /** @return array{type: string, groups: list<array{name: string, label: string, fields: list<array{suffix: string, label: string, description: string}>}>} */
    public function for(ScenarioType $type): array
    {
        return [
            'type' => $type->value,
            'groups' => iterator_to_array($this->serializedGroups($type), false),
        ];
    }

    /** @return iterable<array{name: string, label: string, fields: list<array{suffix: string, label: string, description: string}>}> */
    private function serializedGroups(ScenarioType $type): iterable
    {
        foreach ($this->common->groups() as $group) {
            yield $group->toArray();
        }

        foreach ($this->registry->for($type)->groups() as $group) {
            yield $group->toArray();
        }
    }
}
