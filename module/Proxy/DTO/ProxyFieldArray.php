<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldArray extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('array');
        $this->rules(['array']);
    }
}
