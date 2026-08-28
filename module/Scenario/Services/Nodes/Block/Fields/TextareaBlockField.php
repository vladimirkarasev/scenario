<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class TextareaBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'placeholder' => $this->data->string($this->field, 'placeholder'),
            'defaultValue' => $this->defaultValue,
            'rows' => $this->data->integer($this->field, 'rows', 4),
            'maxLength' => $this->data->integer($this->field, 'maxLength', 3000),
        ]);
    }
}
