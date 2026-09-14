<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class DirectoryBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        $props = [
            ...$this->baseProps(),
            'directoryId' => $this->data->string($this->field, 'directoryId'),
            'versionId' => $this->data->string($this->field, 'versionId'),
            'labelTemplate' => $this->data->string($this->field, 'labelTemplate'),
            'multiple' => $this->data->boolean($this->field, 'multiple'),
            'allowRootSelection' => $this->data->boolean($this->field, 'allowRootSelection', true),
            'defaultSearch' => $this->data->string($this->field, 'defaultSearch'),
            'depDrop' => $this->depDrop(),
        ];

        if ($this->type === 'directory_table') {
            $props['allowSelection'] = $this->data->boolean($this->field, 'allowSelection', true);
            $props['fields'] = is_array($this->field['fields'] ?? null) ? $this->field['fields'] : [];
        }

        return $this->response($props);
    }

    /** @return array{fieldVarName: string, filterKey: string, valueKey: string}|null */
    private function depDrop(): ?array
    {
        $raw = $this->field['depDrop'] ?? null;

        if (!is_array($raw) || !isset($raw['fieldVarName'], $raw['filterKey'])) {
            return null;
        }

        return [
            'fieldVarName' => is_string($raw['fieldVarName']) ? $raw['fieldVarName'] : '',
            'filterKey' => is_string($raw['filterKey']) ? $raw['filterKey'] : '',
            'valueKey' => is_string($raw['valueKey'] ?? null) ? $raw['valueKey'] : 'external_key',
        ];
    }
}
