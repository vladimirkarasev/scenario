<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldUploadList extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('file_list');
        $this->source('files.'.$key);
        $this->rules(['nullable', 'array']);
    }
}
