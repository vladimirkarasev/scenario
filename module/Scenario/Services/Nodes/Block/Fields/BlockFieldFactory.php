<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

use Module\Scenario\Services\Nodes\NodeDataReader;

final readonly class BlockFieldFactory
{
    public function __construct(
        private NodeDataReader $data,
    ) {
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $blockContext
     */
    public function create(array $field, int $index, array $blockContext = []): BlockFieldInterface
    {
        $type = $this->normalizedType($this->data->string($field, 'type', 'input'));

        return match ($type) {
            'rich_text' => new RichTextBlockField(
                $this->data->string($field, 'id', $this->data->string($field, 'name', 'field_'.($index + 1))),
                $field['value'] ?? null,
            ),
            'textarea' => new TextareaBlockField($field, $type, $index, $blockContext, $this->data),
            'number' => new NumberBlockField($field, $type, $index, $blockContext, $this->data),
            'select' => new SelectBlockField($field, $type, $index, $blockContext, $this->data),
            'date', 'datetime' => new DateBlockField($field, $type, $index, $blockContext, $this->data),
            'checkbox' => new CheckboxBlockField($field, $type, $index, $blockContext, $this->data),
            'hidden' => new HiddenBlockField($field, $type, $index, $blockContext, $this->data),
            'suggest' => new SuggestBlockField($field, $type, $index, $blockContext, $this->data),
            'directory_list', 'directory_tree', 'directory_table' => new DirectoryBlockField(
                $field,
                $type,
                $index,
                $blockContext,
                $this->data,
            ),
            'map_point', 'route', 'directory_map' => new MapBlockField(
                $field,
                $type,
                $index,
                $blockContext,
                $this->data,
            ),
            default => new TextBlockField($field, $type, $index, $blockContext, $this->data),
        };
    }

    public function richText(string $id, mixed $content): BlockFieldInterface
    {
        return new RichTextBlockField($id, $content);
    }

    private function normalizedType(string $type): string
    {
        return in_array($type, [
            'textarea', 'number', 'select', 'date', 'datetime', 'hidden', 'email', 'phone', 'vin', 'grz',
            'checkbox', 'directory_list', 'directory_tree', 'directory_table', 'suggest', 'rich_text',
            'map_point', 'route', 'directory_map',
        ], true) ? $type : 'input';
    }
}
