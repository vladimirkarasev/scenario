<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

final readonly class BlockNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private BlockNodeValidator $validator,
    ) {}

    public function isInteractive(array $node): bool
    {
        return ! $this->boolField($this->nodeData($node), 'skipInSurvey');
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
     * @param  array<array-key, mixed> $blocks
     * @param  array<string, mixed>    $context
     * @return mixed
     */
    private function resolveBlocksKeepingRawTemplates(array $blocks, array $context): mixed
    {
        $rawLabelTemplates = [];
        foreach ($blocks as $i => $block) {
            if (! is_array($block)) {
                continue;
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            if (isset($props['labelTemplate']) && is_string($props['labelTemplate'])) {
                $rawLabelTemplates[$i] = $props['labelTemplate'];
            }
        }

        $resolved = $this->variableResolver->resolve($blocks, $context);

        if (! is_array($resolved)) {
            return $resolved;
        }

        foreach ($rawLabelTemplates as $i => $template) {
            if (! isset($resolved[$i]) || ! is_array($resolved[$i])) {
                continue;
            }
            $props = is_array($resolved[$i]['props'] ?? null) ? $resolved[$i]['props'] : [];
            $props['labelTemplate'] = $template;
            $resolved[$i]['props'] = $props;
        }

        return $resolved;
    }

    /**
     * @param  array<array-key, mixed> $field
     * @param  array<string, mixed>    $blockContext Ранее введённые значения юзером для этого блока
     * @return array<string, mixed>
     */
    private function renderField(array $field, int $index, array $blockContext = []): array
    {
        $type = $this->strField($field, 'type', 'input');
        $name = $this->strField($field, 'name', 'field_'.($index + 1));

        // Frontend сохраняет поля в camelCase; читаем оба написания для совместимости.
        $either = function (string $camel, string $snake, string $default = '') use ($field): string {
            $v = $field[$camel] ?? $field[$snake] ?? null;
            return is_scalar($v) ? (string) $v : $default;
        };
        $boolEither = function (string $camel, string $snake, bool $default = false) use ($field): bool {
            $v = $field[$camel] ?? $field[$snake] ?? null;
            return $v === null ? $default : (bool) $v;
        };

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

        $rawOptions = $field['options'] ?? null;

        $blockType = match ($type) {
            'textarea', 'number', 'select', 'date', 'datetime', 'hidden', 'email', 'phone',
            'checkbox', 'directory_list', 'directory_tree', 'directory_table' => $type,
            default => 'input',
        };

        $props = match ($blockType) {
            'checkbox' => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'defaultValue' => $hasUserValue ? (bool) $userValue : $this->boolField($field, 'checked'),
            ],
            'directory_list' => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'directoryId' => $either('directoryId', 'directory_id'),
                'versionId' => $either('versionId', 'version_id'),
                'labelTemplate' => $either('labelTemplate', 'label_template'),
                'labelField' => $either('labelField', 'label_field'),
                'valueField' => $either('valueField', 'value_field'),
                'multiple' => $this->boolField($field, 'multiple'),
            ],
            'directory_tree' => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'directoryId' => $either('directoryId', 'directory_id'),
                'versionId' => $either('versionId', 'version_id'),
                'labelTemplate' => $either('labelTemplate', 'label_template'),
                'labelField' => $either('labelField', 'label_field'),
                'valueField' => $either('valueField', 'value_field'),
                'multiple' => $this->boolField($field, 'multiple'),
                'allowRootSelection' => $boolEither('allowRootSelection', 'allow_root_selection', true),
            ],
            'directory_table' => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'directoryId' => $either('directoryId', 'directory_id'),
                'versionId' => $either('versionId', 'version_id'),
                'labelTemplate' => $either('labelTemplate', 'label_template'),
                'allowSelection' => $boolEither('allowSelection', 'allow_selection', true),
                'multiple' => $this->boolField($field, 'multiple'),
                'defaultSearch' => $either('defaultSearch', 'default_search'),
                'fields' => is_array($field['fields'] ?? null) ? $field['fields'] : [],
            ],
            'textarea' => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'placeholder' => $this->strField($field, 'placeholder'),
                'defaultValue' => $defaultValue,
                'rows' => $this->intField($field, 'rows', 4),
                'maxLength' => $this->intField($field, 'maxLength', 3000),
            ],
            'hidden' => [
                'name' => $name,
                'defaultValue' => $defaultValue,
            ],
            default => [
                'name' => $name,
                'label' => $this->strField($field, 'label', $name),
                'required' => $this->boolField($field, 'required'),
                'placeholder' => $this->strField($field, 'placeholder'),
                ...($hasDefault ? ['defaultValue' => $defaultValue] : []),
                'rows' => $this->intField($field, 'rows', 4),
                'format' => $this->strField($field, 'format', 'DD.MM.YYYY HH:mm'),
                'multiple' => $this->boolField($field, 'multiple'),
                'allowRootSelection' => $this->boolField($field, 'allowRootSelection'),
                'options' => is_array($rawOptions)
                    ? array_values(array_filter($rawOptions, is_array(...)))
                    : [],
            ],
        };

        return [
            'id' => $this->strField($field, 'id', $name),
            'type' => $blockType,
            'props' => $props,
        ];
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
