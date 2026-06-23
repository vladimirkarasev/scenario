<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

final readonly class BlockNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private BlockNodeValidator $validator,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return !$this->boolField($this->nodeData($node), 'skipInSurvey');
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(
            $this->graphResolver->defaultNextNodeId($this->runVersion($run), $this->nodeId($node)),
        );
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $this->validator->validate($this->nodeData($node), $data->input);

        $blockKey = $this->nodeId($node);
        $context = $run->context ?? [];
        $existing = is_array($context[$blockKey] ?? null) ? $context[$blockKey] : [];

        $run->forceFill([
            'context' => array_merge($context, [$blockKey => array_merge($existing, $data->input)]),
        ])->save();

        return $this->graphResolver->defaultNextNodeId($this->runVersion($run), $blockKey);
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData($node);
        $blocks = $this->arrayField($data, 'blocks');

        $text = $this->strField($data, 'text');
        if ($text !== '') {
            array_unshift($blocks, [
                'id' => $this->nodeId($node).'_text',
                'type' => 'rich_text',
                'props' => $this->richTextProps($data['text'] ?? null),
            ]);
        }

        // Ранее введённые значения для этого блока: context[blockNodeId] = [fieldName => value]
        $rawBlockContext = $context[$this->nodeId($node)] ?? null;
        /** @var array<string, mixed> $blockContext */
        $blockContext = is_array($rawBlockContext) ? $rawBlockContext : [];

        foreach ($this->arrayField($data, 'fields') as $index => $field) {
            if (is_array($field) && is_int($index)) {
                $blocks[] = $this->renderField($field, $index, $blockContext);
            }
        }

        return [
            'type' => 'block',
            'title' => $this->variableResolver->resolve($this->strField($data, 'title'), $context),
            'blocks' => $this->resolveBlocksKeepingRawTemplates($blocks, $context),
        ];
    }

    /**
     * Прогоняем переменные сценария через блоки, но оставляем сырыми те поля, которые
     * должны подставляться на фронте из локального источника данных (item.data справочника),
     * — иначе Symfony EL заменит ссылки на отсутствующие переменные на `[]`.
     *
     * @param  array<array-key, mixed>  $blocks
     * @param  array<string, mixed>  $context
     */
    private function resolveBlocksKeepingRawTemplates(array $blocks, array $context): mixed
    {
        $rawLabelTemplates = [];
        foreach ($blocks as $i => $block) {
            if (!is_array($block)) {
                continue;
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            if (isset($props['labelTemplate']) && is_string($props['labelTemplate'])) {
                $rawLabelTemplates[$i] = $props['labelTemplate'];
            }
        }

        $resolved = $this->variableResolver->resolve($blocks, $context);

        if (!is_array($resolved)) {
            return $resolved;
        }

        foreach ($rawLabelTemplates as $i => $template) {
            if (!isset($resolved[$i]) || !is_array($resolved[$i])) {
                continue;
            }
            $props = is_array($resolved[$i]['props'] ?? null) ? $resolved[$i]['props'] : [];
            $props['labelTemplate'] = $template;
            $resolved[$i]['props'] = $props;
        }

        return $resolved;
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @param  array<string, mixed>  $blockContext  Ранее введённые значения юзером для этого блока
     * @return array<string, mixed>
     */
    private function renderField(array $field, int $index, array $blockContext = []): array
    {
        $type = $this->strField($field, 'type', 'input');
        $name = $this->strField($field, 'name', 'field_'.($index + 1));

        // Ранее введённое юзером значение перекрывает default из схемы.
        $hasUserValue = array_key_exists($name, $blockContext);
        $userValue = $hasUserValue ? $blockContext[$name] : null;
        $schemaValue = $field['value'] ?? null;
        $hasDefault = $hasUserValue || ($schemaValue !== null && $schemaValue !== '');
        $defaultValue = $hasUserValue ? $userValue : $schemaValue;

        if ($type === 'rich_text') {
            return [
                'id' => $this->strField($field, 'id', $name),
                'type' => 'rich_text',
                'props' => $this->richTextProps($field['value'] ?? null),
            ];
        }

        $blockType = match ($type) {
            'textarea', 'number', 'select', 'date', 'datetime', 'hidden', 'email', 'phone',
            'checkbox', 'directory_list', 'directory_tree', 'directory_table' => $type,
            default => 'input',
        };

        $props = match ($blockType) {
            'checkbox' => $this->checkboxProps($field, $name, $hasUserValue, $userValue),
            'directory_list' => $this->directoryProps($field, $name),
            'directory_tree' => $this->directoryTreeProps($field, $name),
            'directory_table' => $this->directoryTableProps($field, $name),
            'textarea' => $this->textareaProps($field, $name, $defaultValue),
            'number' => $this->numberProps($field, $name, $hasDefault, $defaultValue),
            'select' => $this->selectProps($field, $name, $hasDefault, $defaultValue),
            'date', 'datetime' => $this->dateProps($field, $name, $hasDefault, $defaultValue),
            'hidden' => $this->hiddenProps($name, $defaultValue),
            default => $this->textProps($field, $name, $hasDefault, $defaultValue), // input / email / phone
        };

        return [
            'id' => $this->strField($field, 'id', $name),
            'type' => $blockType,
            'props' => $props,
        ];
    }

    /**
     * Числовое значение поля (int|float) или null, если не задано (для min/max/step).
     *
     * @param  array<array-key, mixed>  $field
     */
    private function numericField(array $field, string $key): int|float|null
    {
        $value = $field[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Опции select-поля (отфильтрованные массивы).
     *
     * @param  array<array-key, mixed>  $field
     * @return list<array<array-key, mixed>>
     */
    private function fieldOptions(array $field): array
    {
        $raw = $field['options'] ?? null;

        return is_array($raw) ? array_values(array_filter($raw, is_array(...))) : [];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function textProps(array $field, string $name, bool $hasDefault, mixed $defaultValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'placeholder' => $this->strField($field, 'placeholder'),
            ...$this->defaultValueProp($hasDefault, $defaultValue),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function textareaProps(array $field, string $name, mixed $defaultValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'placeholder' => $this->strField($field, 'placeholder'),
            'defaultValue' => $defaultValue,
            'rows' => $this->intField($field, 'rows', 4),
            'maxLength' => $this->intField($field, 'maxLength', 3000),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function numberProps(array $field, string $name, bool $hasDefault, mixed $defaultValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'placeholder' => $this->strField($field, 'placeholder'),
            'min' => $this->numericField($field, 'min'),
            'max' => $this->numericField($field, 'max'),
            'step' => $this->numericField($field, 'step'),
            'decimalPlaces' => $this->intField($field, 'decimalPlaces', 0),
            ...$this->defaultValueProp($hasDefault, $defaultValue),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function selectProps(array $field, string $name, bool $hasDefault, mixed $defaultValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'multiple' => $this->boolField($field, 'multiple'),
            'allowRootSelection' => $this->boolField($field, 'allowRootSelection', true),
            'defaultSearch' => $this->strField($field, 'defaultSearch'),
            'options' => $this->fieldOptions($field),
            ...$this->defaultValueProp($hasDefault, $defaultValue),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function dateProps(array $field, string $name, bool $hasDefault, mixed $defaultValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'format' => $this->strField($field, 'format', 'DD.MM.YYYY HH:mm'),
            ...$this->defaultValueProp($hasDefault, $defaultValue),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function checkboxProps(array $field, string $name, bool $hasUserValue, mixed $userValue): array
    {
        return [
            ...$this->baseProps($field, $name),
            'defaultValue' => $hasUserValue ? (bool)$userValue : $this->boolField($field, 'checked'),
        ];
    }

    /** @return array<string, mixed> */
    private function hiddenProps(string $name, mixed $defaultValue): array
    {
        return ['name' => $name, 'defaultValue' => $defaultValue];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function directoryProps(array $field, string $name): array
    {
        return [
            ...$this->baseProps($field, $name),
            'directoryId' => $this->strField($field, 'directoryId'),
            'versionId' => $this->strField($field, 'versionId'),
            'labelTemplate' => $this->strField($field, 'labelTemplate'),
            'multiple' => $this->boolField($field, 'multiple'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function directoryTreeProps(array $field, string $name): array
    {
        return [
            ...$this->directoryProps($field, $name),
            'allowRootSelection' => $this->boolField($field, 'allowRootSelection', true),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<string, mixed>
     */
    private function directoryTableProps(array $field, string $name): array
    {
        return [
            ...$this->directoryProps($field, $name),
            'allowSelection' => $this->boolField($field, 'allowSelection', true),
            'defaultSearch' => $this->strField($field, 'defaultSearch'),
            'fields' => is_array($field['fields'] ?? null) ? $field['fields'] : [],
        ];
    }

    /**
     * Базовые props, общие для большинства полей: имя, подпись, обязательность.
     *
     * @param  array<array-key, mixed>  $field
     * @return array{name: string, label: string, required: bool}
     */
    private function baseProps(array $field, string $name): array
    {
        return [
            'name' => $name,
            'label' => $this->strField($field, 'label', $name),
            'required' => $this->boolField($field, 'required'),
        ];
    }

    /**
     * defaultValue добавляется только если он реально задан (юзером или схемой).
     *
     * @return array{defaultValue?: mixed}
     */
    private function defaultValueProp(bool $hasDefault, mixed $defaultValue): array
    {
        return $hasDefault ? ['defaultValue' => $defaultValue] : [];
    }

    /**
     * Нормализует содержимое rich-text в формат документа или html.
     *
     * @return array<string, mixed>
     */
    private function richTextProps(mixed $content): array
    {
        if (is_array($content) && ($content['type'] ?? null) === 'doc') {
            return ['document' => $content];
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);

            if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
                return ['document' => $decoded];
            }

            return ['html' => $content];
        }

        return ['document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]];
    }
}
