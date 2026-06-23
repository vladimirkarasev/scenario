<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldFileUrl extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('file_url');
        $this->rules(['string', 'url', 'max:2048']);
    }
}
