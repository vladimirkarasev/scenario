<?php

declare(strict_types=1);

namespace Module\Scenario\DTO\Variables;

final class VariableEntryFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): VariableEntryInterface
    {
        $fieldType = is_string($data['_field_type'] ?? null) ? $data['_field_type'] : 'input';

        return match (true) {
            $fieldType === 'select' => SelectVariableEntry::fromArray($data),
            in_array($fieldType, ['date', 'datetime'], true) => DateVariableEntry::fromArray($data),
            in_array($fieldType, ['directory_list', 'directory_table'], true) => DirectoryVariableEntry::fromArray(
                $data,
            ),
            default => SimpleVariableEntry::fromArray($data),
        };
    }
}
