<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldFileUrlList extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('file_url_list');
        $this->rules(['array']);
    }
}
