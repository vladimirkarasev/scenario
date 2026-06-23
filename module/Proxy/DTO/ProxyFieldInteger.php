<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldInteger extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('integer');
    }
}
