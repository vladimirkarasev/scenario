<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class DateBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'format' => $this->data->string($this->field, 'format', 'DD.MM.YYYY HH:mm'),
            ...$this->defaultValueProp(),
        ]);
    }
}
