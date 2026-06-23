<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldArrayList extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('array_list');
        $this->rules(['array', 'list']);
    }
}
