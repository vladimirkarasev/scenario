<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldBoolean extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('boolean');
        $this->rules(['boolean']);
    }

    /** @param bool $value */
    public function default(mixed $value): static
    {
        return parent::default($value);
    }
}
