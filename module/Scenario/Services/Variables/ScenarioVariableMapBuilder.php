<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables;

use Module\Scenario\Enums\ScenarioNodeType;

final readonly class ScenarioVariableMapBuilder
{
    /**
     * @param  array<string, mixed>  $schemaJson
     * @return array<string, array<string, mixed>>
     */
    public function build(array $schemaJson): array
    {
        $map = [];
        $rawBlocks = $schemaJson['blocks'] ?? ($schemaJson['nodes'] ?? []);
        $blocks = is_array($rawBlocks) ? $rawBlocks : [];

        foreach ($blocks as $block) {
            if (!is_array($block) || !$this->isContentNode($block['type'] ?? null)) {
                continue;
            }

            $blockId = is_string($block['id'] ?? null) ? $block['id'] : '';
            $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
            $rawFields = is_array($blockData['fields'] ?? null) ? $blockData['fields'] : [];

            foreach ($rawFields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $varName = is_string($field['varName'] ?? null) ? trim($field['varName']) : '';
                $name = is_string($field['name'] ?? null) ? trim($field['name']) : '';
                $fieldType = is_string($field['type'] ?? null) ? $field['type'] : 'input';

                if ($varName === '' || $name === '') {
                    continue;
                }

                $entry = [
                    '_block_id' => $blockId,
                    '_field_name' => $name,
                    '_field_type' => $fieldType,
                ];

                if ($fieldType === 'select') {
                    $rawOptions = is_array($field['options'] ?? null) ? $field['options'] : [];
                    $entry['_options'] = array_values(array_filter($rawOptions, is_array(...)));
                } elseif (in_array($fieldType, ['date', 'datetime'], true)) {
                    $entry['_format'] = is_string($field['format'] ?? null) ? $field['format'] : '';
                } elseif (in_array($fieldType, ['directory_list', 'directory_table'], true)) {
                    $entry['_directory_id'] = is_string($field['directoryId'] ?? null) ? $field['directoryId'] : '';
                    $entry['_version_id'] = is_string($field['versionId'] ?? null) ? $field['versionId'] : '';
                    $entry['_label_template'] = is_string($field['labelTemplate'] ?? null)
                        ? $field['labelTemplate']
                        : '';
                    $entry['_multiple'] = (bool)($field['multiple'] ?? false);
                }

                $map[$varName] = $entry;
            }
        }

        return $map;
    }

    private function isContentNode(mixed $type): bool
    {
        return is_string($type) && ScenarioNodeType::tryFrom($type)?->isContent() === true;
    }
}
