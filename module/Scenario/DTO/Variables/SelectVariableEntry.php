<?php

declare(strict_types=1);

namespace Module\Scenario\DTO\Variables;

final readonly class SelectVariableEntry implements VariableEntryInterface
{
    /**
     * @param  list<array<mixed>>  $options  Статические опции [{id, value, label, parentId}, ...]
     */
    public function __construct(
        private string $blockId,
        private string $fieldName,
        private array $options,
    ) {
    }

    public function blockId(): string
    {
        return $this->blockId;
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    public function fieldType(): string
    {
        return 'select';
    }

    /**
     * @return list<array<mixed>>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function toArray(): array
    {
        return [
            '_block_id' => $this->blockId,
            '_field_name' => $this->fieldName,
            '_field_type' => 'select',
            '_options' => $this->options,
        ];
    }

    public static function fromArray(array $data): static
    {
        $rawOpts = $data['_options'] ?? [];
        $options = is_array($rawOpts)
            ? array_values(array_filter($rawOpts, is_array(...)))
            : [];

        return new static(
            blockId: is_string($data['_block_id'] ?? null) ? $data['_block_id'] : '',
            fieldName: is_string($data['_field_name'] ?? null) ? $data['_field_name'] : '',
            options: $options,
        );
    }
}
