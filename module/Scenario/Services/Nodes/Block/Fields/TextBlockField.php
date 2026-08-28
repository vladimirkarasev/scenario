<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class TextBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'placeholder' => $this->data->string($this->field, 'placeholder'),
            ...$this->defaultValueProp(),
        ]);
    }
}
