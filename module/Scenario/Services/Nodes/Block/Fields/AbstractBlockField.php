<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

use Module\Scenario\Services\Nodes\NodeDataReader;

abstract readonly class AbstractBlockField implements BlockFieldInterface
{
    protected string $id;

    protected string $name;

    protected bool $hasUserValue;

    protected mixed $defaultValue;

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $blockContext
     */
    public function __construct(
        protected array $field,
        protected string $type,
        int $index,
        array $blockContext,
        protected NodeDataReader $data,
    ) {
        $this->name = $this->data->string($field, 'name', 'field_'.($index + 1));
        $this->id = $this->data->string($field, 'id', $this->name);
        $this->hasUserValue = array_key_exists($this->name, $blockContext);
        $schemaValue = $field['value'] ?? null;
        $this->defaultValue = $this->hasUserValue ? $blockContext[$this->name] : $schemaValue;
    }

    /** @return array<string, mixed> */
    protected function baseProps(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->data->string($this->field, 'label', $this->name),
            'hideLabel' => $this->data->boolean($this->field, 'hideLabel'),
            'required' => $this->data->boolean($this->field, 'required'),
            ...$this->labelStyleProps(),
        ];
    }

    /** @return array{defaultValue?: mixed} */
    protected function defaultValueProp(): array
    {
        $schemaValue = $this->field['value'] ?? null;
        $hasDefault = $this->hasUserValue || ($schemaValue !== null && $schemaValue !== '');

        return $hasDefault ? ['defaultValue' => $this->defaultValue] : [];
    }

    protected function numeric(string $key): int|float|null
    {
        $value = $this->field[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? $value + 0 : null;
    }

    /** @return list<array<array-key, mixed>> */
    protected function options(): array
    {
        $raw = $this->field['options'] ?? null;

        return is_array($raw) ? array_values(array_filter($raw, is_array(...))) : [];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{id: string, type: string, props: array<string, mixed>}
     */
    protected function response(array $props): array
    {
        return (new RenderedBlockField($this->id, $this->type, $props))->toArray();
    }

    /** @return array{labelFontSize?: string, labelColor?: string, labelHighlight?: string} */
    private function labelStyleProps(): array
    {
        $props = [];

        foreach (['labelFontSize', 'labelColor', 'labelHighlight'] as $key) {
            $value = $this->data->string($this->field, $key);
            if ($value !== '') {
                $props[$key] = $value;
            }
        }

        return $props;
    }
}
