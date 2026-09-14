<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class CheckboxBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'defaultValue' => $this->hasUserValue
                ? (bool) $this->defaultValue
                : $this->data->boolean($this->field, 'checked'),
        ]);
    }
}
