<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class SelectBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'multiple' => $this->data->boolean($this->field, 'multiple'),
            'allowRootSelection' => $this->data->boolean($this->field, 'allowRootSelection', true),
            'defaultSearch' => $this->data->string($this->field, 'defaultSearch'),
            'options' => $this->options(),
            ...$this->defaultValueProp(),
        ]);
    }
}
