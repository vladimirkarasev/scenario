<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

final readonly class SystemVariableField
{
    public function __construct(
        public string $suffix,
        public string $label,
        public string $description,
    ) {
    }

    /** @return array{suffix: string, label: string, description: string} */
    public function toArray(): array
    {
        return [
            'suffix' => $this->suffix,
            'label' => $this->label,
            'description' => $this->description,
        ];
    }
}
