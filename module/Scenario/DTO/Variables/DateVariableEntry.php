<?php

declare(strict_types=1);

namespace Module\Scenario\DTO\Variables;

final readonly class DateVariableEntry implements VariableEntryInterface
{
    /**
     * @param  'date'|'datetime'  $fieldType
     * @param  string  $format  Момент-формат, напр. 'DD.MM.YYYY HH:mm'
     */
    public function __construct(
        private string $blockId,
        private string $fieldName,
        private string $fieldType,
        private string $format,
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
        return $this->fieldType;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function toArray(): array
    {
        return [
            '_block_id' => $this->blockId,
            '_field_name' => $this->fieldName,
            '_field_type' => $this->fieldType,
            '_format' => $this->format,
        ];
    }

    public static function fromArray(array $data): static
    {
        $fieldType = is_string($data['_field_type'] ?? null) && $data['_field_type'] === 'datetime'
            ? 'datetime'
            : 'date';

        return new static(
            blockId: is_string($data['_block_id'] ?? null) ? $data['_block_id'] : '',
            fieldName: is_string($data['_field_name'] ?? null) ? $data['_field_name'] : '',
            fieldType: $fieldType,
            format: is_string($data['_format'] ?? null) ? $data['_format'] : '',
        );
    }
}
