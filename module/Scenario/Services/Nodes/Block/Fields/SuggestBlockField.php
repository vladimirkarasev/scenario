<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class SuggestBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response([
            ...$this->baseProps(),
            'proxyUuid' => $this->data->string($this->field, 'proxyUuid'),
            'labelField' => $this->data->string($this->field, 'labelField'),
            'placeholder' => $this->data->string($this->field, 'placeholder'),
            'multiple' => $this->data->boolean($this->field, 'multiple'),
        ]);
    }
}
