<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioContextKey;

final readonly class SystemVariableGroup
{
    /** @param list<SystemVariableField> $fields */
    public function __construct(
        public ScenarioContextKey $name,
        public array $fields,
    ) {
    }

    /** @return array{name: string, label: string, fields: list<array{suffix: string, label: string, description: string}>} */
    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'label' => $this->name->label(),
            'fields' => array_map(
                static fn(SystemVariableField $field): array => $field->toArray(),
                $this->fields,
            ),
        ];
    }
}
