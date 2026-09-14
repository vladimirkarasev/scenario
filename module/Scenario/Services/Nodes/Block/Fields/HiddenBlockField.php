<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class HiddenBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response(['name' => $this->name, 'defaultValue' => $this->defaultValue]);
    }
}
