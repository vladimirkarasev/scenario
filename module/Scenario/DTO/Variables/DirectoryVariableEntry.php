<?php

declare(strict_types=1);

namespace Module\Scenario\DTO\Variables;

final readonly class DirectoryVariableEntry implements VariableEntryInterface
{
    /**
     * @param  'directory_list'|'directory_table'  $fieldType
     */
    public function __construct(
        private string $blockId,
        private string $fieldName,
        private string $fieldType,
        private string $directoryId,
        private string $versionId,
        private string $labelTemplate,
        private bool $multiple,
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

    public function directoryId(): string
    {
        return $this->directoryId;
    }

    public function versionId(): string
    {
        return $this->versionId;
    }

    public function labelTemplate(): string
    {
        return $this->labelTemplate;
    }

    public function multiple(): bool
    {
        return $this->multiple;
    }

    public function toArray(): array
    {
        return [
            '_block_id' => $this->blockId,
            '_field_name' => $this->fieldName,
            '_field_type' => $this->fieldType,
            '_directory_id' => $this->directoryId,
            '_version_id' => $this->versionId,
            '_label_template' => $this->labelTemplate,
            '_multiple' => $this->multiple,
        ];
    }

    public static function fromArray(array $data): static
    {
        $fieldType = is_string($data['_field_type'] ?? null) && $data['_field_type'] === 'directory_table'
            ? 'directory_table'
            : 'directory_list';

        return new static(
            blockId: is_string($data['_block_id'] ?? null) ? $data['_block_id'] : '',
            fieldName: is_string($data['_field_name'] ?? null) ? $data['_field_name'] : '',
            fieldType: $fieldType,
            directoryId: is_string($data['_directory_id'] ?? null) ? $data['_directory_id'] : '',
            versionId: is_string($data['_version_id'] ?? null) ? $data['_version_id'] : '',
            labelTemplate: is_string($data['_label_template'] ?? null) ? $data['_label_template'] : '',
            multiple: (bool)($data['_multiple'] ?? false),
        );
    }
}
