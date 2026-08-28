<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class NumberBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'placeholder' => $this->data->string($this->field, 'placeholder'),
            'min' => $this->numeric('min'),
            'max' => $this->numeric('max'),
            'step' => $this->numeric('step'),
            'decimalPlaces' => $this->data->integer($this->field, 'decimalPlaces'),
            ...$this->defaultValueProp(),
        ]);
    }
}
