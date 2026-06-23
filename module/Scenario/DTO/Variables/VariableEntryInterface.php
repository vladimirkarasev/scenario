<?php

declare(strict_types=1);

namespace Module\Scenario\DTO\Variables;

interface VariableEntryInterface
{
    public function blockId(): string;

    public function fieldName(): string;

    public function fieldType(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static;
}
